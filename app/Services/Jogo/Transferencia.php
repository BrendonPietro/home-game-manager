<?php

namespace App\Services\Jogo;

/** Um acerto: quem paga, quem recebe e quanto (centavos). Participante nulo = o caixa (a casa). */
final readonly class Transferencia
{
    public function __construct(
        public ?int $de,
        public ?int $para,
        public int $valor,
    ) {}
}
