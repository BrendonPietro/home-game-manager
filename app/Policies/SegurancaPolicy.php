<?php

namespace App\Policies;

use App\Models\Seguranca;
use App\Support\Permissoes;
use Illuminate\Database\Eloquent\Model;

/**
 * Contas do painel. Só se mexe em conta que não tem mais poder que a sua (Admin não troca a senha do
 * Desenvolvedor) e ninguém exclui a própria conta.
 */
class SegurancaPolicy extends PoliticaDoPainel
{
    protected const RECURSO = 'seguranca';

    public function update(Seguranca $conta, Model $outra): bool
    {
        return parent::update($conta, $outra) && Permissoes::abrange($conta, $outra->getAllPermissions());
    }

    public function delete(Seguranca $conta, Model $outra): bool
    {
        return parent::delete($conta, $outra)
            && $conta->isNot($outra)
            && Permissoes::abrange($conta, $outra->getAllPermissions());
    }
}
