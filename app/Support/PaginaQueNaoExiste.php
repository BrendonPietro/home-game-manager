<?php

namespace App\Support;

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Endereço que não existe (jogo excluído, link antigo, digitado errado) não mostra o 404: volta para o
 * início do lado certo, com um aviso. No site, para a home (`session('aviso')`, que a home mostra); no
 * painel, para o Painel de controle (notificação do Filament).
 *
 * Continuam no 404 o que não é página aberta pela pessoa: pedido que não é GET (Livewire, formulário),
 * pedido de JSON e arquivo (`/build/app.js`, `/favicon.ico`). O link do relógio sem jogo tem a própria
 * página ("link não vale mais"), que não passa por aqui. No site, o poll do Livewire que dá 404 (o jogo
 * foi excluído com a tela aberta) recarrega a página (conexao.js), e a página cai aqui.
 */
class PaginaQueNaoExiste
{
    public static function redirecionar(Request $request): ?RedirectResponse
    {
        if (! $request->isMethod('GET') || $request->expectsJson() || str_contains(basename($request->path()), '.')) {
            return null;
        }

        $aviso = $request->is('jogos/*', 'painel/jogos/*', 'painel/modelos/*') ? 'Esse jogo não existe mais.' : 'Essa página não existe.';

        if ($request->is('painel', 'painel/*')) {
            Notification::make()->warning()->title($aviso)->send();

            return redirect(Filament::getPanel('painel')->getUrl());
        }

        return redirect()->route('home')->with('aviso', $aviso);
    }
}
