<?php

namespace App\Models;

use Spatie\Permission\Models\Role;

/**
 * Grupo de permissões (tabela `grupos`). Painel: Admin, Desenvolvedor, Geral (guard `painel`).
 * Site: Admin, Operador (guard `web`). Checar sempre a permissão, nunca o nome do grupo.
 */
class Grupo extends Role
{
}
