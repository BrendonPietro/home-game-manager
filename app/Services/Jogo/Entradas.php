<?php

namespace App\Services\Jogo;

use App\Enums\TipoItem;
use App\Models\Entrada;
use App\Models\Jogo;
use App\Support\Dinheiro;
use Illuminate\Support\Collection;

/** Entradas válidas do jogo por jogador (assentos da tela ao vivo e tabela do resumo). */
class Entradas
{
    /** @return Collection<int, EntradasDoJogador> pelo id do participante (só quem tem entrada) */
    public function porJogador(Jogo $jogo): Collection
    {
        return $jogo->entradas()->validas()->with('item')->get()
            ->groupBy('participante_id')
            ->map(fn (Collection $doJogador, int $participanteId) => new EntradasDoJogador(
                participanteId: $participanteId,
                buyin: $doJogador->first(fn (Entrada $e) => $e->tipo === TipoItem::Buyin)?->item,
                rebuys: $doJogador->filter(fn (Entrada $e) => $e->tipo === TipoItem::Rebuy)->count(),
                addons: $doJogador->filter(fn (Entrada $e) => $e->tipo === TipoItem::Addon)->count(),
                opcionais: $doJogador->filter(fn (Entrada $e) => $e->tipo === TipoItem::Opcional)->count(),
                total: $doJogador->sum(fn (Entrada $e) => Dinheiro::centavos($e->valor)),
            ));
    }
}
