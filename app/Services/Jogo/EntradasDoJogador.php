<?php

namespace App\Services\Jogo;

use App\Models\Item;

/** O que um jogador comprou no jogo (entradas válidas): buy-in, contagens e total em centavos (fichas não se somam por jogador). */
final readonly class EntradasDoJogador
{
    public function __construct(
        public int $participanteId,
        public ?Item $buyin,
        public int $rebuys,
        public int $addons,
        public int $opcionais,
        public int $total,
    ) {}
}
