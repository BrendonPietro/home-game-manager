<?php

namespace App\Actions\Jogo;

use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Services\Jogo\EventoDoJogo;
use Illuminate\Support\Facades\DB;

/** Rascunho ou agendado → cancelado. O jogo sai da agenda; nada é apagado. */
class CancelarJogo
{
    public function __construct(private EventoDoJogo $evento) {}

    public function __invoke(Jogo $jogo): Jogo
    {
        return DB::transaction(function () use ($jogo) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);

            if (! $jogo->status->podeCancelar()) {
                throw new RegraDoJogo('Só jogo em rascunho ou agendado pode ser cancelado.');
            }

            $jogo->forceFill(['status' => StatusJogo::Cancelado])->save();
            $this->evento->sincronizar($jogo);

            return $jogo;
        });
    }
}
