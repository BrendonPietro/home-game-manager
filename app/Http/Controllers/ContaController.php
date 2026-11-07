<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Minha conta (site): quem entrou troca a própria senha, com a senha atual. Clicar no avatar da topbar
 * leva para cá. Nome, e-mail e o resto do cadastro continuam no painel (Usuários).
 */
class ContaController extends Controller
{
    public function editar(): View
    {
        return view('site.pages.conta', ['usuario' => auth('web')->user()]);
    }

    public function atualizar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'senha_atual' => ['required', 'current_password:web'],
            'senha' => ['required', 'confirmed', 'different:senha_atual', Password::min(8)],
        ], [
            'senha_atual.current_password' => 'A senha atual não confere.',
            'senha.different' => 'A senha nova precisa ser diferente da atual.',
        ], [
            'senha_atual' => 'senha atual',
            'senha' => 'senha nova',
        ]);

        // O cast `hashed` guarda o hash; o "Lembrar acesso" de outros aparelhos deixa de valer.
        $usuario = $request->user('web');
        $usuario->forceFill(['password' => $dados['senha'], 'remember_token' => Str::random(60)])->save();
        $request->session()->regenerate();

        return redirect()->route('conta')->with('aviso', 'Senha trocada');
    }
}
