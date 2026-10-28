<?php

namespace App\Policies;

use App\Enums\TipoEvento;
use App\Models\Seguranca;
use Illuminate\Database\Eloquent\Model;

class EventoPolicy extends PoliticaDoPainel
{
    protected const RECURSO = 'eventos';

    /** O evento do jogo se altera pelo cadastro do jogo. */
    public function update(Seguranca $conta, Model $evento): bool
    {
        return parent::update($conta, $evento) && $evento->tipo !== TipoEvento::Jogo;
    }

    public function delete(Seguranca $conta, Model $evento): bool
    {
        return parent::delete($conta, $evento) && $evento->tipo !== TipoEvento::Jogo;
    }
}
