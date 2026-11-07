<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Acertos;
use App\Services\Jogo\Historico;
use App\Services\Ranking\Pontuacao;
use Illuminate\Support\Facades\DB;

/**
 * Em andamento → finalizado (tela 14). Torneio: entradas fechadas e a posição de todos que jogaram.
 * Cash game: o cash-out de todos que jogaram. Grava os acertos sugeridos (quem paga quem) e os
 * pontos dos rankings. Daí em diante o jogo é só leitura, menos o pagamento de pendências.
 */
class FinalizarJogo
{
    use ChecaRegras;

    public function __construct(
        private Acertos $acertos,
        private Pontuacao $pontuacao,
        private Historico $historico,
    ) {}

    public function __invoke(Jogo $jogo, Usuario $quem): Jogo
    {
        $this->permitir($quem, 'jogo.finalizar', 'Só o Admin do site finaliza o jogo.');

        return DB::transaction(function () use ($jogo, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'Só jogo em andamento pode ser finalizado.');

            $jogaram = $jogo->participantes()->with('usuario')
                ->whereHas('entradas', fn ($entradas) => $entradas->validas()->where('tipo', TipoItem::Buyin))
                ->orderBy('id')
                ->get();

            if ($jogaram->isEmpty()) {
                throw new RegraDoJogo('Ninguém fez buy-in neste jogo.');
            }

            if ($jogo->eTorneio()) {
                if ($jogo->fase !== FaseTorneio::SemEntradas) {
                    throw new RegraDoJogo('Feche as entradas antes de finalizar.');
                }

                $faltam = $jogaram->whereNull('posicao_final');
                $falta = 'Falta a posição de ';
            } else {
                $faltam = $jogaram->whereNull('cash_out');
                $falta = 'Falta o cash-out de ';
            }

            if ($faltam->isNotEmpty()) {
                throw new RegraDoJogo($falta.$faltam->map(fn (Participante $p) => $p->nome())->join(', ', ' e ').'.');
            }

            $jogo->forceFill(['status' => StatusJogo::Finalizado, 'finalizado_em' => now()])->save();

            $this->acertos->refazerEmAberto($jogo);
            $this->pontuacao->gravar($jogo);

            $this->historico->registrar($jogo, $quem, TipoAlteracao::Finalizou, 'Finalizou o jogo ('.$jogaram->count().' jogadores, '.$jogo->acertos()->count().' acertos)');

            return $jogo;
        });
    }
}
