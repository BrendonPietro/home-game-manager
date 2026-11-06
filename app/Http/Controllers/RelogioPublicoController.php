<?php

namespace App\Http\Controllers;

use App\Enums\StatusJogo;
use App\Models\Jogo;
use App\Services\Jogo\Relogio;
use App\Support\EtapaDoJogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * v3: o relógio do torneio para outras telas (TV, tablet, celular, notebook), pelo link
 * `/relogio/{codigo}`: sem login e só para ver. Mostra só o relógio (nível, tempo, blinds, ante, o que
 * vem depois e o intervalo), nada de dinheiro nem de jogadores.
 *
 * A página carrega uma vez; o motor do relógio (relogio-torneio.js) busca o estado em
 * `/relogio/{codigo}/estado` a cada 2 s, sem sessão, e anda sozinho se a internet cair. Quando a
 * `chave` muda (o jogo começou, acabou, mudou o nível digitado), a página recarrega. Link trocado ou
 * jogo fora do site: 404, e a página avisa que o link não vale mais.
 */
class RelogioPublicoController extends Controller
{
    public function mostrar(string $codigo, Relogio $relogio): View|Response
    {
        $jogo = $this->jogo($codigo);

        if ($jogo === null) {
            return response()->view('site.pages.relogio-invalido', status: 404);
        }

        $estado = $relogio->estado($jogo);

        return view('site.pages.relogio', [
            'jogo' => $jogo,
            'estado' => $estado,
            'pacote' => $this->pacote($jogo, $estado),
        ]);
    }

    public function estado(string $codigo, Relogio $relogio): JsonResponse
    {
        $jogo = $this->jogo($codigo);

        if ($jogo === null) {
            return response()->json(['erro' => 'Link do relógio não encontrado.'], 404);
        }

        return response()
            ->json($this->pacote($jogo, $relogio->estado($jogo)))
            ->header('Cache-Control', 'no-store');
    }

    private function jogo(string $codigo): ?Jogo
    {
        return Jogo::query()
            ->where('relogio_codigo', $codigo)
            ->where('e_modelo', false)
            ->whereIn('status', [...EtapaDoJogo::STATUS_NO_SITE, StatusJogo::Cancelado])
            ->with('estruturaBlinds.niveis')
            ->first();
    }

    /**
     * O que a página e o motor precisam: o status, a `chave` (quando muda, a página recarrega), o
     * pacote do relógio (com estrutura) e o nível digitado na tela ao vivo (sem estrutura).
     *
     * @return array{status: string, chave: string, relogio: ?array<string, mixed>, manual: ?array{titulo: string, blinds: ?string}, aviso: array{numero: int, ha: ?float}}
     */
    private function pacote(Jogo $jogo, mixed $estado): array
    {
        $manual = $estado === null && $jogo->status === StatusJogo::EmAndamento
            ? ['titulo' => $jogo->nivel_atual ? 'Nível '.$jogo->nivel_atual : 'Blinds', 'blinds' => $jogo->blinds_atual]
            : null;

        return [
            'status' => $jogo->status->value,
            'chave' => md5(json_encode([$jogo->status->value, $jogo->nome, $estado !== null, $manual])),
            'relogio' => $estado?->paraNavegador(),
            'manual' => $manual,
            'aviso' => $jogo->avisoParaNavegador(), // o AVISO do gerenciador (som em todas as telas)
        ];
    }
}
