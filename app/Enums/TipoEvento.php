<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

enum TipoEvento: string
{
    use TemOpcoes;

    case Jogo = 'jogo';
    case Aluguel = 'aluguel';
    case Reserva = 'reserva';
    case Bloqueio = 'bloqueio';

    public function label(): string
    {
        return match ($this) {
            self::Jogo => 'Jogo',
            self::Aluguel => 'Aluguel',
            self::Reserva => 'Reserva',
            self::Bloqueio => 'Bloqueio',
        };
    }
}
