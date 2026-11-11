<?php

namespace App\Actions\Jogo;

use App\Models\Jogo;
use App\Models\Seguranca;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Exclui o jogo de vez, pelo painel, com tudo o que foi lançado nele: lista, lugares, entradas, pagamentos,
 * eliminações, acertos, pontos dos rankings, histórico e o evento da agenda. Quem pode diz a JogoPolicy:
 * com `jogos.excluir`, só rascunho sem entradas (o resto se cancela); com `jogos.excluir_tudo` (o
 * Desenvolvedor), qualquer jogo, também em andamento e finalizado.
 */
class ExcluirJogo
{
    public const PERMISSAO_TUDO = 'jogos.excluir_tudo';

    public function __invoke(Jogo $jogo, Seguranca $quem): void
    {
        DB::transaction(function () use ($jogo, $quem) {
            $jogo = Jogo::query()->lockForUpdate()->findOrFail($jogo->id);

            Gate::forUser($quem)->authorize('delete', $jogo);

            // As entradas saem primeiro: o item do jogo não se apaga com entrada apontando para ele, e o
            // vínculo entre entradas (opcional ligado ao buy-in) se solta antes. O resto vai pelo cascade.
            $jogo->entradas()->update(['vinculada_a' => null]);
            $jogo->entradas()->delete();
            $jogo->delete();
        });
    }

    /** O que o modal de excluir avisa: "Apaga o jogo e tudo o que foi lançado nele: 30 jogadores, 2 entradas (R$ 200) e o histórico." */
    public static function oQueApaga(Jogo $jogo): string
    {
        $entradas = $jogo->entradas()->validas();
        $quantas = (clone $entradas)->count();

        $partes = array_filter([
            self::contar($jogo->participantes()->count(), 'jogador', 'jogadores'),
            $quantas > 0 ? self::contar($quantas, 'entrada', 'entradas').' ('.Dinheiro::brl((clone $entradas)->sum('valor')).')' : null,
            self::contar($jogo->pagamentos()->validas()->count(), 'pagamento', 'pagamentos'),
            self::contar($jogo->eliminacoes()->validas()->count(), 'eliminação', 'eliminações'),
            $jogo->pontosRanking()->exists() ? 'os pontos dos rankings' : null,
            $jogo->alteracoes()->exists() ? 'o histórico' : null,
        ]);

        if ($partes === []) {
            return 'O jogo sai do painel e do calendário. Não dá para desfazer.';
        }

        $ultima = array_pop($partes);
        $lista = $partes === [] ? $ultima : implode(', ', $partes).' e '.$ultima;

        return "Apaga o jogo e tudo o que foi lançado nele: {$lista}. Não dá para desfazer.";
    }

    private static function contar(int $quantos, string $um, string $varios): ?string
    {
        return $quantos > 0 ? $quantos.' '.($quantos === 1 ? $um : $varios) : null;
    }
}
