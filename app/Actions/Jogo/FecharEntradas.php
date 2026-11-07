<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\Historico;
use App\Services\Jogo\Posicoes;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;

/**
 * Torneio: intervalo → sem entradas. Buy-in, rebuy e add-on fecham; quem estava fora e não voltou
 * fica eliminado de vez, na ordem em que caiu; o prêmio de cada posição é gravado (jogo_premiacoes, pela
 * regra do número de jogadores ou pelos percentuais manuais) e quem já caiu ganha a posição. Daqui em
 * diante cada eliminação dá a posição na hora.
 */
class FecharEntradas
{
    use ChecaRegras;

    public function __construct(
        private Calculadora $calculadora,
        private Posicoes $posicoes,
        private Historico $historico,
    ) {}

    public function __invoke(Jogo $jogo, Usuario $quem): Jogo
    {
        $this->permitir($quem, 'jogo.fases', 'Só o Admin do site muda a fase do torneio.');

        return DB::transaction(function () use ($jogo, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'O jogo não está em andamento.');

            if ($jogo->eCashGame()) {
                throw new RegraDoJogo('Cash game não tem fases.');
            }

            match ($jogo->fase) {
                FaseTorneio::Rebuys => throw new RegraDoJogo('Abra o intervalo antes de fechar as entradas.'),
                FaseTorneio::SemEntradas => throw new RegraDoJogo('As entradas já estão fechadas.'),
                default => null,
            };

            $resumo = $this->calculadora->doJogo($jogo);

            if ($resumo->jogadores < 2) {
                throw new RegraDoJogo('Fechar entradas pede pelo menos 2 jogadores com buy-in.');
            }

            if ($resumo->premios === []) {
                throw new RegraDoJogo("Nenhuma regra de premiação cobre {$resumo->jogadores} jogadores: ajuste no painel.");
            }

            $this->posicoes->gravarPremios($jogo, $resumo->premios);

            // Quem caiu e não voltou com rebuy sai de vez (a ordem da queda dá a posição).
            $jogo->eliminacoes()->validas()->emAberto()->update(['definitiva' => true]);

            $jogo->forceFill([
                'fase' => FaseTorneio::SemEntradas,
                'entradas_fechadas_em' => now(),
            ])->save();

            $this->posicoes->recalcular($jogo);

            $this->historico->registrar($jogo, $quem, TipoAlteracao::FechouEntradas,
                'Fechou as entradas: '.$resumo->jogadores.' jogadores, prize pool '.Dinheiro::brlCentavos($resumo->prizePool),
                dados: ['premios' => $resumo->premios],
            );

            return $jogo;
        });
    }
}
