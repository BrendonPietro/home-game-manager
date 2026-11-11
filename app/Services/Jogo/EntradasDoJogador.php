<?php

namespace App\Services\Jogo;

use App\Models\Item;

/**
 * O que um jogador comprou no jogo (entradas válidas): buy-in, contagens, total em centavos e as fichas que
 * ele recebeu (as de cada entrada, opcionais incluídos, mais o time chip e o bônus do pagamento antecipado).
 */
final readonly class EntradasDoJogador
{
    public function __construct(
        public int $participanteId,
        public ?Item $buyin,
        public int $rebuys,
        public int $addons,
        public int $opcionais,
        public int $total,
        public int $fichas = 0,
        public int $timeChip = 0,
        public int $antecipado = 0,
    ) {}
}
