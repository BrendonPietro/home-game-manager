<?php

namespace App\Support;

use App\Models\Configuracao;

/**
 * "Arredondar prêmios para R$ 5" (Configurações, tela 25; chave `premios.arredondar`, ligada por padrão).
 * Do 2º lugar para baixo o prêmio desce para o múltiplo de R$ 5 e a diferença vai para o 1º.
 * Vale ao fechar as entradas (é quando os prêmios são gravados); quem já fechou não muda.
 */
class ArredondarPremios
{
    public const CHAVE = 'premios.arredondar';

    /** R$ 5 em centavos. */
    public const MULTIPLO = 500;

    public static function ativo(): bool
    {
        return (bool) Configuracao::valor(self::CHAVE, true);
    }

    public static function definir(bool $ativo): void
    {
        Configuracao::definir(self::CHAVE, $ativo);
    }

    /** Desce para o múltiplo de R$ 5; um prêmio menor que R$ 5 fica como está (não vira zero). */
    public static function paraBaixo(int $centavos): int
    {
        $arredondado = intdiv($centavos, self::MULTIPLO) * self::MULTIPLO;

        return $arredondado > 0 ? $arredondado : $centavos;
    }
}
