<?php

namespace App\Policies;

use App\Models\Seguranca;
use Illuminate\Database\Eloquent\Model;

class MesaPolicy extends PoliticaDoPainel
{
    protected const RECURSO = 'mesas';

    /** Mesa com aluguel, reserva ou bloqueio na agenda não se exclui: desative. */
    public function delete(Seguranca $conta, Model $mesa): bool
    {
        return parent::delete($conta, $mesa) && ! $mesa->eventos()->exists();
    }
}
