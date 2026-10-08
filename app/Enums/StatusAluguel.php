<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

enum StatusAluguel: string
{
    use TemOpcoes;

    case Reservado = 'reservado';
    case Confirmado = 'confirmado';
    case Pago = 'pago';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Reservado => 'Reservado',
            self::Confirmado => 'Confirmado',
            self::Pago => 'Pago',
            self::Cancelado => 'Cancelado',
        };
    }
}
