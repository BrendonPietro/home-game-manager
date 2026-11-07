<?php

namespace App\Http\Middleware;

use App\Enums\StatusJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Support\EtapaDoJogo;
use App\Support\JanelaDoSite;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quem pode continuar no site, a cada página e a cada clique do Livewire (middleware persistente):
 *
 * - Conta desativada no painel (`ativo` ou `acessa_site` desligado) sai na hora, também com "Lembrar acesso".
 * - Sem a permissão `site.fora_da_noite` (o Operador), o site só mostra o torneio dentro da janela dele
 *   (App\Support\JanelaDoSite: de 3 h antes do início a 3 h depois do término previsto; em andamento,
 *   até finalizar). Fora dela a pessoa continua logada e fica em "Nenhum torneio agora" (`sem-torneio`).
 *
 * Sai só do site (`guard web`): a sessão é a mesma do painel, que continua logado no mesmo navegador.
 */
class AcessoDoSite
{
    public const FORA_DA_NOITE = 'site.fora_da_noite';

    /** Status → aba da lista de jogos do site. */
    private const ABAS = [
        'agendado' => 'proximos',
        'em_andamento' => 'andamento',
        'finalizado' => 'finalizados',
    ];

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
            return $request->routeIs('sem-torneio') ? redirect()->route('home') : $next($request);
        }

        $abertos = JanelaDoSite::jogosAbertos();

        if ($abertos->isEmpty()) {
            return $request->routeIs('sem-torneio') ? $next($request) : redirect()->route('sem-torneio');
        }

        if ($request->routeIs('sem-torneio')) {
            return redirect($this->destino($abertos));
        }

        return $this->foraDaJanela($request, $abertos) ?? $next($request);
    }

    /**
     * Com jogo na janela: outro jogo ou o começo do site levam para ele.
     *
     * @param  Collection<int, Jogo>  $abertos
     */
    private function foraDaJanela(Request $request, Collection $abertos): ?Response
    {
        $jogo = $request->route('jogo');

        if ($jogo instanceof Jogo && ! $abertos->contains('id', $jogo->id)) {
            return redirect($this->destino($abertos));
        }

        if ($request->routeIs('home')) {
            return redirect($this->destino($abertos));
        }

        $abas = $abertos->map(fn (Jogo $j): string => self::ABAS[$j->status->value])->unique()->values();

        if ($request->routeIs('jogos.index') && ! $abas->contains($request->query('aba', 'proximos'))) {
            return redirect()->route('jogos.index', ['aba' => $abas->first()]);
        }

        return null;
    }

    /**
     * Um jogo só: a etapa dele. Mais de um (raro): a lista na aba do primeiro.
     *
     * @param  Collection<int, Jogo>  $abertos
     */
    private function destino(Collection $abertos): string
    {
        if ($abertos->count() === 1) {
            return EtapaDoJogo::url($abertos->first()) ?? route('jogos.index');
        }

        $primeiro = $abertos->firstWhere('status', StatusJogo::EmAndamento) ?? $abertos->first();

        return route('jogos.index', ['aba' => self::ABAS[$primeiro->status->value]]);
    }

    private function sair(Request $request): void
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->regenerateToken();
        }
    }
}
