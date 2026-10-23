<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as Contrato;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Depois do login do painel, volta para a página pretendida só se ela for do painel.
 * A sessão é a mesma do site: sem isso, quem tentou abrir o site antes cairia no login do site.
 */
class LoginResponse implements Contrato
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $painel = Filament::getUrl();
        $pretendida = (string) session()->pull('url.intended');

        $doPainel = $pretendida === $painel || str_starts_with($pretendida, rtrim($painel, '/').'/');

        return redirect()->to($doPainel ? $pretendida : $painel);
    }
}
