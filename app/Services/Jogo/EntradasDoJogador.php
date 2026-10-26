<?php

namespace App\Services\Jogo;

use App\Models\Item;

/** O que um jogador comprou no jogo (entradas válidas): buy-in, contagens, fichas e total em centavos. */
final readonly class EntradasDoJogador
{
    public function __construct(
        public int $participanteId,
        public ?Item $buyin,
        public int $rebuys,
        public int $addons,
        public int $opcionais,
        public int $fichas,
        public int $total,
    ) {}
}
