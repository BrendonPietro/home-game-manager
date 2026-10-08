<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

/** Torneio ou cash game (jogos.modalidade). */
enum Modalidade: string
{
    use TemOpcoes;

    case Torneio = 'torneio';
    case CashGame = 'cash_game';

    public function label(): string
    {
        return match ($this) {
            self::Torneio => 'Torneio',
            self::CashGame => 'Cash game',
        };
    }
}
