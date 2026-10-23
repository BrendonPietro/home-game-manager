<?php

namespace App\Services\Jogo;

use App\Enums\StatusJogo;
use App\Enums\TipoEvento;
use App\Models\Evento;
use App\Models\Jogo;

/**
 * Mantém o evento da agenda de um jogo: criar um jogo cria o evento (docs/regras.md).
 * Modelo, jogo sem data e jogo cancelado não ficam na agenda.
 */
class EventoDoJogo
{
    public function sincronizar(Jogo $jogo): ?Evento
    {
        if ($jogo->e_modelo || $jogo->data === null || $jogo->status === StatusJogo::Cancelado) {
            $jogo->evento()->delete();

            return null;
        }

        $evento = Evento::query()->updateOrCreate(
            ['jogo_id' => $jogo->id],
            [
                'tipo' => TipoEvento::Jogo,
                'nome' => $jogo->nome,
                'data' => $jogo->data,
                'hora_inicio' => $jogo->horario,
            ],
        );

        $evento->mesas()->sync($jogo->mesas()->pluck('mesa_id'));

        return $evento;
    }
}
