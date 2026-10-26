<?php

namespace App\Services\Jogo;

use App\Enums\TipoItem;
use App\Models\Jogo;

/**
 * Posição e prêmio de quem caiu, com as entradas fechadas (docs/regras.md, "Fases de um torneio").
 * Jogadores = quem tem buy-in válido (N). A k-ésima eliminação definitiva, na ordem em que caíram,
 * fica em N − k; quando sobra um só, ele é o 1º. Prêmio = valor gravado da posição (jogo_premiacoes).
 *
 * Refaz tudo a cada chamada: desfazer uma eliminação no meio da lista empurra as seguintes.
 */
class Posicoes
{
    public function recalcular(Jogo $jogo): void
    {
        $comBuyin = $jogo->entradas()->validas()->where('tipo', TipoItem::Buyin)->distinct()->pluck('participante_id');
        $caidos = $jogo->eliminacoes()->validas()->definitivas()->orderBy('created_at')->orderBy('id')->pluck('participante_id');

        $posicoes = [];

        foreach ($caidos->values() as $k => $participanteId) {
            $posicoes[$participanteId] = $comBuyin->count() - $k;
        }

        $restantes = $comBuyin->diff($caidos);

        if ($restantes->count() === 1) {
            $posicoes[$restantes->first()] = 1;
        }

        $premios = $jogo->premiacoes()->pluck('valor', 'posicao');

        foreach ($jogo->participantes()->get() as $participante) {
            $posicao = $posicoes[$participante->id] ?? null;

            $participante->forceFill([
                'posicao_final' => $posicao,
                'premio' => $posicao ? ($premios[$posicao] ?? null) : null,
            ])->save();
        }
    }
}
