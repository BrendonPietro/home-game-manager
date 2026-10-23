<?php

namespace App\Policies;

use App\Models\Seguranca;
use Illuminate\Database\Eloquent\Model;

class MesaPolicy extends PoliticaDoPainel
{
    protected const RECURSO = 'mesas';

    /** Mesa que já foi usada em algum jogo não se exclui (o banco também barra): desative. */
    public function delete(Seguranca $conta, Model $mesa): bool
    {
        return parent::delete($conta, $mesa) && ! $mesa->jogoMesas()->exists();
    }
}
