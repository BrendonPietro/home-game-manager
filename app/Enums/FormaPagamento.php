<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

/**
 * Como o pagamento foi feito. Cartão e Outro continuam aqui só para ler o que já foi gravado:
 * nas telas, por enquanto, só Pix e Dinheiro (`disponiveis()`).
 */
enum FormaPagamento: string
{
    use TemOpcoes;

    case Pix = 'pix';
    case Dinheiro = 'dinheiro';
    case Cartao = 'cartao';
    case Outro = 'outro';

    /** @return list<self> as formas que aparecem nas telas e que as Actions aceitam */
    public static function disponiveis(): array
    {
        return [
            self::Pix,
            self::Dinheiro,
            // self::Cartao, // sem uso por enquanto
        ];
    }

    public function disponivel(): bool
    {
        return in_array($this, self::disponiveis(), true);
    }

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
