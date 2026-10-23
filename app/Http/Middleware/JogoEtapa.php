<?php

namespace App\Http\Middleware;

use App\Models\Jogo;
use App\Support\EtapaDoJogo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * jogo.etapa:conferencia — quem abre uma etapa que o status do jogo não permite volta para a
 * etapa atual (ou para a lista de jogos) com um aviso. Jogo fora do site (rascunho, cancelado,
 * modelo) volta para a lista.
 */
class JogoEtapa
{
    public function handle(Request $request, Closure $next, string $etapa): Response
    {
        /** @var Jogo $jogo */
        $jogo = $request->route('jogo');

        if ($jogo->e_modelo || ! in_array($jogo->status, EtapaDoJogo::STATUS_NO_SITE, true)) {
            return redirect()->route('jogos.index')->with('aviso', "{$jogo->nome} não está disponível no site.");
        }

        if (! EtapaDoJogo::permite($jogo, $etapa)) {
            $rotulo = EtapaDoJogo::ETAPAS[$etapa][0] ?? $etapa;
            $aviso = "{$rotulo} não abre agora: o jogo está ".mb_strtolower($jogo->status->label()).'.';

            return redirect(EtapaDoJogo::url($jogo) ?? route('jogos.index'))->with('aviso', $aviso);
        }

        return $next($request);
    }
}
