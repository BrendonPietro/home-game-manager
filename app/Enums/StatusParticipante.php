<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

enum StatusParticipante: string
{
    use TemOpcoes;

    case Confirmado = 'confirmado';
    case Jogando = 'jogando';
    case Eliminado = 'eliminado';
    case Saiu = 'saiu';

    public function label(): string
    {
        return match ($this) {
            self::Confirmado => 'Confirmado',
            self::Jogando => 'Jogando',
            self::Eliminado => 'Eliminado',
            self::Saiu => 'Saiu',
        };
    }
}
