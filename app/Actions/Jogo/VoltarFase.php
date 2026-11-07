<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\FaseTorneio;
use App\Enums\MetodoPremiacao;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Jogo\Historico;
use App\Services\Jogo\Posicoes;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Correção de fase apertada antes da hora (permissão `jogo.corrigir`, com motivo, fica no histórico):
 *
 * - Intervalo → rebuys: o intervalo volta a estar fechado (add-ons já lançados continuam valendo).
 * - Sem entradas → intervalo (REABRIR ENTRADAS): buy-in, rebuy e add-on voltam; as eliminações de quem
 *   não voltou ficam provisórias de novo (quem caiu ainda pode voltar), as posições e os prêmios gravados
 *   saem e são refeitos no próximo FECHAR ENTRADAS.
 */
class VoltarFase
{
    use ChecaRegras;

    public function __construct(
        private Posicoes $posicoes,
        private Historico $historico,
    ) {}

    public function __invoke(Jogo $jogo, string $motivo, Usuario $quem): Jogo
    {
        $this->permitir($quem, 'jogo.corrigir', 'Você não tem permissão para voltar a fase do torneio.');

        $motivo = Str::limit(trim($motivo), 255, '');

        if ($motivo === '') {
            throw new RegraDoJogo('Diga o motivo para voltar a fase.');
        }

        return DB::transaction(function () use ($jogo, $motivo, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'A fase só volta com o jogo em andamento.');

            if ($jogo->eCashGame()) {
                throw new RegraDoJogo('Cash game não tem fases.');
            }

            match ($jogo->fase) {
                FaseTorneio::Intervalo => $this->voltarAosRebuys($jogo, $motivo, $quem),
                FaseTorneio::SemEntradas => $this->reabrirEntradas($jogo, $motivo, $quem),
                default => throw new RegraDoJogo('O torneio já está na primeira fase.'),
            };

            return $jogo->refresh();
        });
    }

    private function voltarAosRebuys(Jogo $jogo, string $motivo, Usuario $quem): void
    {
        $antes = $jogo->intervalo_aberto_em?->toIso8601String();

        $jogo->forceFill(['fase' => FaseTorneio::Rebuys, 'intervalo_aberto_em' => null])->save();

        $this->historico->registrar($jogo, $quem, TipoAlteracao::VoltouAosRebuys, 'Voltou da fase de intervalo para a de rebuys', $motivo, [
            'intervalo_aberto_em' => $antes,
        ]);
    }

    private function reabrirEntradas(Jogo $jogo, string $motivo, Usuario $quem): void
    {
        $premios = $jogo->premiacoes()->get()->map(fn ($p) => ['posicao' => $p->posicao, 'valor' => $p->valor])->all();
        $posicoes = $jogo->participantes()->whereNotNull('posicao_final')->pluck('posicao_final', 'id')->all();

        // Quem caiu e não voltou volta a poder voltar; os prêmios e as posições saem até fechar de novo.
        $jogo->eliminacoes()->validas()->whereNull('voltou_com')->update(['definitiva' => false]);

        if ($jogo->metodo_premiacao === MetodoPremiacao::Manual) {
            $jogo->premiacoes()->update(['valor' => null]); // os percentuais manuais continuam
        } else {
            $jogo->premiacoes()->delete();
        }

        $jogo->forceFill(['fase' => FaseTorneio::Intervalo, 'entradas_fechadas_em' => null])->save();
        $this->posicoes->recalcular($jogo);

        $prizePool = array_sum(array_map(fn (array $p) => Dinheiro::centavos($p['valor']), $premios));

        $this->historico->registrar($jogo, $quem, TipoAlteracao::ReabriuEntradas, 'Reabriu as entradas (prize pool que estava gravado: '.Dinheiro::brlCentavos($prizePool).')', $motivo, [
            'premios' => $premios,
            'posicoes' => $posicoes,
        ]);
    }
}
