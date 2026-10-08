<?php

namespace App\Enums\Concerns;

/**
 * Para enums com label(): opções prontas para selects (Filament e Blade).
 */
trait TemOpcoes
{
    /** @return array<string, string> valor => rótulo */
    public static function opcoes(): array
    {
        $opcoes = [];

        foreach (self::cases() as $caso) {
            $opcoes[$caso->value] = $caso->label();
        }

        return $opcoes;
    }
}
