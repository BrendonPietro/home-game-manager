<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

/** regra = percentuais de regras_premiacao pela quantidade de jogadores; manual = jogo_premiacoes digitadas. */
enum MetodoPremiacao: string
{
    use TemOpcoes;

    case Regra = 'regra';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Regra => 'Pela regra do clube',
            self::Manual => 'Manual',
        };
    }
}
