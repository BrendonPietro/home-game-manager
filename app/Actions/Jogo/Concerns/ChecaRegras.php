<?php

namespace App\Actions\Jogo\Concerns;

use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;

/** As duas checagens de toda Action do site: a permissão de quem clica e o status do jogo. */
trait ChecaRegras
{
    protected function permitir(Usuario $quem, string $permissao, string $mensagem): void
    {
        if (! $quem->can($permissao)) {
            throw new AuthorizationException($mensagem);
        }
    }

    /** @param  list<StatusJogo>  $status */
    protected function exigirStatus(Jogo $jogo, array $status, string $mensagem): void
    {
        if (! in_array($jogo->status, $status, true)) {
            throw new RegraDoJogo($mensagem);
        }
    }
}
