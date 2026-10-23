<?php

namespace App\Policies;

use App\Models\Seguranca;
use Illuminate\Database\Eloquent\Model;

/**
 * Base das policies do painel: cada ação do Filament consulta a permissão `recurso.acao` da conta
 * (guard `painel`). Nunca compara nome de grupo.
 *
 * Quem estende define RECURSO (ex.: 'mesas') e pode restringir mais (ex.: não excluir o que está em uso).
 */
abstract class PoliticaDoPainel
{
    protected const RECURSO = '';

    protected function pode(Seguranca $conta, string $acao): bool
    {
        return $conta->ativo && $conta->can(static::RECURSO.'.'.$acao);
    }

    public function viewAny(Seguranca $conta): bool
    {
        return $this->pode($conta, 'ver');
    }

    public function view(Seguranca $conta, Model $registro): bool
    {
        return $this->pode($conta, 'ver');
    }

    public function create(Seguranca $conta): bool
    {
        return $this->pode($conta, 'criar');
    }

    public function update(Seguranca $conta, Model $registro): bool
    {
        return $this->pode($conta, 'editar');
    }

    public function delete(Seguranca $conta, Model $registro): bool
    {
        return $this->pode($conta, 'excluir');
    }

    public function deleteAny(Seguranca $conta): bool
    {
        return $this->pode($conta, 'excluir');
    }

    public function restore(Seguranca $conta, Model $registro): bool
    {
        return $this->pode($conta, 'excluir');
    }

    public function restoreAny(Seguranca $conta): bool
    {
        return $this->pode($conta, 'excluir');
    }

    // Nada some de vez pelo painel.
    public function forceDelete(Seguranca $conta, Model $registro): bool
    {
        return false;
    }

    public function forceDeleteAny(Seguranca $conta): bool
    {
        return false;
    }
}
