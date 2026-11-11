<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Tela 01: login do site de operação (guard `web`, tabela `usuarios`), pelo usuário (`login`) ou pelo e-mail.
 * Só entra quem tem `acessa_site` e está ativo; jogador sem login nunca passa daqui.
 */
class AuthController extends Controller
{
    private const MAX_TENTATIVAS = 5;

    public function create(): View
    {
        return view('site.pages.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], ['login' => 'usuário ou e-mail']);

        // Com @ é o e-mail; sem, o usuário (usuarios.login não aceita @). Os dois gravados em minúsculas.
        $login = Str::lower(trim($dados['login']));
        $campo = str_contains($login, '@') ? 'email' : 'login';
        $chave = 'login-site|'.$login.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($chave, self::MAX_TENTATIVAS)) {
            throw ValidationException::withMessages([
                'login' => 'Muitas tentativas. Tente de novo em '.RateLimiter::availableIn($chave).' segundos.',
            ]);
        }

        $entrou = Auth::guard('web')->attempt([
            $campo => $login,
            'password' => $dados['password'],
            'acessa_site' => true,
            'ativo' => true,
        ], $request->boolean('remember'));

        if (! $entrou) {
            RateLimiter::hit($chave);

            throw ValidationException::withMessages([
                'login' => 'Usuário ou senha incorretos.',
            ]);
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate();

        // Volta para a página pretendida só se ela for do site (a sessão é a mesma do painel).
        $pretendida = (string) $request->session()->pull('url.intended');
        $painel = url('painel');
        $doPainel = $pretendida === $painel || str_starts_with($pretendida, $painel.'/');

        return redirect()->to($pretendida !== '' && ! $doPainel ? $pretendida : route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
