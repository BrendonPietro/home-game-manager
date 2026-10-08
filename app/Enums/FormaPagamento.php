<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

enum FormaPagamento: string
{
    use TemOpcoes;

    case Pix = 'pix';
    case Dinheiro = 'dinheiro';
    case Cartao = 'cartao';
    case Outro = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'Pix',
            self::Dinheiro => 'Dinheiro',
            self::Cartao => 'Cartão',
            self::Outro => 'Outro',
        };
    }
}
