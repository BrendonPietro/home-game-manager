<?php

namespace App\Policies;

/** Usuários (todas as pessoas do clube). Excluir é soft delete: jogos, pontos e dinheiro continuam ligados. */
class UsuarioPolicy extends PoliticaDoPainel
{
    protected const RECURSO = 'usuarios';
}
