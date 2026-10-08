<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

/** Tipo de jogo_itens / entradas. buyin, rebuy e addon são entradas; opcional é o resto. */
enum TipoItem: string
{
    use TemOpcoes;

    case Buyin = 'buyin';
    case Rebuy = 'rebuy';
    case Addon = 'addon';
    case Opcional = 'opcional';

    public function label(): string
    {
        return match ($this) {
            self::Buyin => 'Buy-in',
            self::Rebuy => 'Rebuy',
            self::Addon => 'Add-on',
            self::Opcional => 'Opcional',
        };
    }

    public function eEntrada(): bool
    {
        return $this !== self::Opcional;
    }

    /** @return list<self> */
    public static function entradas(): array
    {
        return [self::Buyin, self::Rebuy, self::Addon];
    }
}
