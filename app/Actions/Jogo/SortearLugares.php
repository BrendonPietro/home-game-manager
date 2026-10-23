<?php

namespace App\Actions\Jogo;

use App\Actions\Jogo\Concerns\ChecaRegras;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Sorteia mesa e lugar de todos os confirmados, antes de começar. As mesas ficam equilibradas
 * (cada uma recebe gente na proporção dos seus lugares) e os lugares dentro da mesa são aleatórios.
 */
class SortearLugares
{
    use ChecaRegras;

    public function __invoke(Jogo $jogo, Usuario $quem): void
    {
        $this->permitir($quem, 'mesas.organizar', 'Você não tem permissão para sortear os lugares.');

        DB::transaction(function () use ($jogo) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);
            $this->exigirStatus($jogo, [StatusJogo::Agendado], 'Sortear lugares só antes de começar.');

            $mesas = $jogo->mesas()->get();

            if ($mesas->isEmpty()) {
                throw new RegraDoJogo('Adicione uma mesa antes de sortear.');
            }

            $jogadores = $jogo->participantes()->where('status', StatusParticipante::Confirmado)->get()->shuffle()->values();
            $capacidade = $mesas->sum('lugares');

            if ($jogadores->count() > $capacidade) {
                $faltam = $jogadores->count() - $capacidade;

                throw new RegraDoJogo("Faltam {$faltam} lugares: adicione uma mesa ou aumente os lugares.");
            }

            // Quantos vão para cada mesa: sempre a mesa menos cheia (proporcionalmente) que ainda tem lugar.
            $quantos = $mesas->mapWithKeys(fn (JogoMesa $m) => [$m->id => 0])->all();
            foreach ($jogadores as $_) {
                $mesa = $mesas
                    ->filter(fn (JogoMesa $m) => $quantos[$m->id] < $m->lugares)
                    ->sortBy(fn (JogoMesa $m) => sprintf('%08.4f-%04d', $quantos[$m->id] / $m->lugares, $m->ordem))
                    ->first();
                $quantos[$mesa->id]++;
            }

            $jogo->participantes()->update(['jogo_mesa_id' => null, 'lugar' => null]);

            $proximo = 0;
            foreach ($mesas as $mesa) {
                $lugares = collect(range(1, $mesa->lugares))->shuffle()->take($quantos[$mesa->id]);

                foreach ($lugares as $lugar) {
                    $jogadores[$proximo++]->update(['jogo_mesa_id' => $mesa->id, 'lugar' => $lugar]);
                }
            }
        });
    }
}
