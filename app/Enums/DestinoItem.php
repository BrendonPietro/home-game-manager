<?php

namespace App\Enums;

use App\Enums\Concerns\TemOpcoes;

/** Quanto do item vai para a premiação: vai (100%), 50/50 ou não vai. */
enum DestinoItem: string
{
    use TemOpcoes;

    case Premiacao = 'premiacao';
    case Metade = 'metade';
    case Fora = 'fora';

    public function label(): string
    {
        return match ($this) {
            self::Premiacao => 'Vai para a premiação',
            self::Metade => '50/50',
            self::Fora => 'Não vai para a premiação',
        };
    }

    /** Fração que entra na premiação bruta, em percentual inteiro (evita float). */
    public function percentual(): int
    {
        return match ($this) {
            self::Premiacao => 100,
            self::Metade => 50,
            self::Fora => 0,
        };
    }
}
