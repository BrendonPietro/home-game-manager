<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

enum Periodicidade: string
{
    use TemOpcoes;

    case Semanal = 'semanal';
    case Mensal = 'mensal';
    case Anual = 'anual';
    case Livre = 'livre';

    public function label(): string
    {
        return match ($this) {
            self::Semanal => 'Semanal',
            self::Mensal => 'Mensal',
            self::Anual => 'Anual',
            self::Livre => 'Livre',
        };
    }
}
