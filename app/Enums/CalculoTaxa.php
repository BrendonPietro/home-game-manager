<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

/** Como uma taxa (jogo_taxas) é calculada. Todas saem da premiação antes de dividir. */
enum CalculoTaxa: string
{
    use TemOpcoes;

    case Fixo = 'fixo';
    case Percentual = 'percentual';
    case PorUnidade = 'por_unidade';
    case PorJogador = 'por_jogador';
    case PorEntrada = 'por_entrada';

    public function label(): string
    {
        return match ($this) {
            self::Fixo => 'Valor fixo',
            self::Percentual => '% da premiação bruta',
            self::PorUnidade => 'Valor × quantidade',
            self::PorJogador => 'Por jogador',
            self::PorEntrada => 'Por entrada',
        };
    }
}
