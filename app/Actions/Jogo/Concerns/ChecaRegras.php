<?php

namespace App\Actions\Jogo\Concerns;

use App\Enums\StatusJogo;
use App\Exceptions\RegraDoJogo;
use App\Models\Jogo;
use App\Models\Seguranca;
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

    /**
     * Ação que existe no site e no painel (lista da noite, pagamento antecipado): no site vale a
     * permissão do site; no painel, a do painel, com a conta ativa.
     */
    protected function permitirNosDois(Usuario|Seguranca $quem, string $doSite, string $doPainel, string $mensagem): void
    {
        $pode = $quem instanceof Seguranca
            ? $quem->ativo && $quem->can($doPainel)
            : $quem->can($doSite);

        if (! $pode) {
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
