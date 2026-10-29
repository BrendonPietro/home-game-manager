<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Posicoes;
use App\Services\Jogo\Relogio;
use Illuminate\Support\Facades\DB;

/**
 * Torneio com as entradas fechadas: define a posição de quem ainda está em jogo (tela 13), para
 * acabar a noite sem jogar até o fim (acordo na mesa final, horário). Quem está em jogo ocupa as
 * posições de 1º a Nº; cada um vira uma eliminação definitiva, do último colocado para o 2º, e o
 * 1º fica. Assim as posições continuam saindo só das eliminações (Posicoes).
 */
class DefinirResultado
{
    use ChecaRegras;

    public function __construct(private Posicoes $posicoes, private Relogio $relogio) {}

    /** @param  array<int|string, int|string>  $posicoes  participanteId => posição */
    public function __invoke(Jogo $jogo, array $posicoes, Usuario $quem): void
    {
        $this->permitir($quem, 'jogo.fases', 'Só o Admin do site define o resultado.');

        DB::transaction(function () use ($jogo, $posicoes, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::EmAndamento], 'O resultado só se define com o jogo em andamento.');

            if (! $jogo->eTorneio()) {
                throw new RegraDoJogo('Cash game não tem posições: o resultado sai dos cash-outs.');
            }

            if ($jogo->fase !== FaseTorneio::SemEntradas) {
                throw new RegraDoJogo('Feche as entradas antes de definir o resultado.');
            }

            $emJogo = $jogo->participantes()->naMesa()
                ->whereHas('entradas', fn ($entradas) => $entradas->validas()->where('tipo', TipoItem::Buyin))
                ->pluck('id');

            if ($emJogo->count() < 2) {
                throw new RegraDoJogo('O resultado já está definido.');
            }

            $posicoes = collect($posicoes)->mapWithKeys(fn ($posicao, $id) => [(int) $id => (int) $posicao]);

            if ($posicoes->keys()->sort()->values()->all() !== $emJogo->sort()->values()->all()) {
                throw new RegraDoJogo('Defina a posição de todos que ainda estão em jogo.');
            }

            if ($posicoes->sort()->values()->all() !== range(1, $emJogo->count())) {
                throw new RegraDoJogo("Quem está em jogo fica do 1º ao {$emJogo->count()}º, sem repetir.");
            }

            $this->relogio->avancar($jogo); // com relógio, o nível é o de agora

            // Do último colocado para o 2º: a ordem das eliminações dá a posição.
            foreach ($posicoes->sortDesc() as $participanteId => $posicao) {
                if ($posicao === 1) {
                    continue;
                }

                $jogo->eliminacoes()->create([
                    'participante_id' => $participanteId,
                    'nivel' => $jogo->nivel_atual,
                    'definitiva' => true,
                    'registrada_por' => $quem->id,
                ]);

                Participante::query()->whereKey($participanteId)->update(['status' => StatusParticipante::Eliminado]);
            }

            $this->posicoes->recalcular($jogo);
        });
    }
}
