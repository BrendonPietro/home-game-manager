<?php

namespace App\Policies;

use App\Models\Seguranca;
use Illuminate\Database\Eloquent\Model;

/** Grupos de permissões dos dois lados. Grupo com alguém dentro não se exclui. */
class GrupoPolicy extends PoliticaDoPainel
{
    protected const RECURSO = 'grupos';

    public function delete(Seguranca $conta, Model $grupo): bool
    {
        return parent::delete($conta, $grupo) && $grupo->membros() === 0;
    }
}
