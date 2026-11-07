<?php

namespace App\Http\Middleware;

use App\Enums\StatusJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Support\EtapaDoJogo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quem pode continuar no site, a cada página e a cada clique do Livewire (middleware persistente):
 *
 * - Conta desativada no painel (`ativo` ou `acessa_site` desligado) sai na hora, também com "Lembrar acesso".
 * - Sem a permissão `site.fora_da_noite` (o Operador), o site só existe com um jogo em andamento: as
 *   telas são as desse jogo; acabou o jogo, a sessão acaba e fica a página "Nenhum torneio em andamento".
 *
 * Sai só do site (`guard web`): a sessão é a mesma do painel, que continua logado no mesmo navegador.
 */
class AcessoDoSite
{
    public const FORA_DA_NOITE = 'site.fora_da_noite';

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user('web');

        if (! $usuario instanceof Usuario) {
            return $next($request);
        }

        if (! $usuario->ativo || ! $usuario->acessa_site) {
            $this->sair($request);

            return redirect()->route('login')
                ->withErrors(['email' => 'Seu acesso ao site foi desativado. Fale com o organizador.']);
        }

        if ($usuario->can(self::FORA_DA_NOITE)) {
            return $next($request);
        }

        $emAndamento = Jogo::query()->reais()->comStatus(StatusJogo::EmAndamento)
            ->orderBy('data')->orderBy('horario')->orderBy('id')
            ->get();

        if ($emAndamento->isEmpty()) {
            $this->sair($request);

            return redirect()->route('sem-torneio');
        }

        return $this->foraDoJogo($request, $emAndamento) ?? $next($request);
    }

    /**
     * Com jogo em andamento: outro jogo (agendado, finalizado) ou o começo do site levam para a noite.
     *
     * @param  Collection<int, Jogo>  $emAndamento
     */
    private function foraDoJogo(Request $request, Collection $emAndamento): ?Response
    {
        $jogo = $request->route('jogo');
        $daNoite = fn (): string => $emAndamento->count() === 1
            ? EtapaDoJogo::url($emAndamento->first(), 'ao-vivo')
            : route('jogos.index', ['aba' => 'andamento']);

        if ($jogo instanceof Jogo && ! $emAndamento->contains('id', $jogo->id)) {
            return redirect($daNoite());
        }

        if ($request->routeIs('home')) {
            return redirect($daNoite());
        }

        if ($request->routeIs('jogos.index') && $request->query('aba') !== 'andamento') {
            return redirect()->route('jogos.index', ['aba' => 'andamento']);
        }

        return null;
    }

    private function sair(Request $request): void
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->regenerateToken();
        }
    }
}
