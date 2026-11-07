<?php

namespace App\Support;

use App\Models\Configuracao;

/**
 * "Arredondar prêmios para R$ 5" (Configurações, tela 25; chave `premios.arredondar`, ligada por padrão).
 * Do 2º lugar para baixo o prêmio desce para o múltiplo de R$ 5 e a diferença vai para o 1º; desligado,
 * desce para o real (prêmio de torneio nunca tem centavos). Vale ao fechar as entradas (é quando os
 * prêmios são gravados); quem já fechou não muda.
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

    /**
     * Desce para o múltiplo (R$ 5 por padrão). Prêmio que viraria zero desce para o real; menor que
     * R$ 1, fica como está (nunca vira zero).
     */
    public static function paraBaixo(int $centavos, int $multiplo = self::MULTIPLO): int
    {
        foreach (array_unique([$multiplo, Dinheiro::REAL]) as $passo) {
            $arredondado = intdiv($centavos, $passo) * $passo;

            if ($arredondado > 0) {
                return $arredondado;
            }
        }

        return $centavos;
    }
}
