<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Grupo de permissões (tabela `grupos`). Painel: Admin, Desenvolvedor, Geral (guard `painel`).
 * Site: Admin, Operador (guard `web`). Checar sempre a permissão, nunca o nome do grupo.
 */
class Grupo extends Role
{
    public const GUARDS = ['painel' => 'Painel', 'web' => 'Site'];

    /** Contas (painel) ou usuários (site) neste grupo. */
    public function membros(): int
    {
        return DB::table(config('permission.table_names.model_has_roles'))->where('role_id', $this->getKey())->count();
    }
}
