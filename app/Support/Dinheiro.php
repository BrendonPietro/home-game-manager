<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Dinheiro em centavos inteiros: o banco guarda decimal(10,2) e as contas nunca somam float.
 *
 *   Dinheiro::centavos('1890.00')  → 189000
 *   Dinheiro::decimal(189000)      → '1890.00'
 *   Dinheiro::brl('1890.00')       → 'R$ 1.890'
 *   Dinheiro::brl('-200', true)    → '−R$ 200'   (no Blade: @brl($valor) / @brl($valor, true))
 */
class Dinheiro
{
    /** Valor em reais (decimal do banco, string do formulário ou int) → centavos. */
    public static function centavos(string|int|float|null $reais): int
    {
        if ($reais === null || $reais === '') {
            return 0;
        }

        if (is_int($reais)) {
            return $reais * 100;
        }

        if (is_float($reais)) {
            $reais = number_format($reais, 2, '.', '');
        }

        $texto = trim($reais);

        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $texto, $partes)) {
            throw new InvalidArgumentException("Valor em reais inválido: [{$reais}]");
        }

        $centavos = (int) $partes[2] * 100 + (int) str_pad($partes[3] ?? '0', 2, '0');

        return $partes[1] === '-' ? -$centavos : $centavos;
    }

    /** Centavos → decimal para gravar no banco ('1890.00'). */
    public static function decimal(int $centavos): string
    {
        $sinal = $centavos < 0 ? '-' : '';
        $centavos = abs($centavos);

        return $sinal.intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Reais → 'R$ 1.890'. Centavos só aparecem quando existem ('R$ 12,50').
     * Com $sinal: '+R$ 795' / '−R$ 200' (sinal de menos tipográfico); zero fica 'R$ 0'.
     */
    public static function brl(string|int|float|null $reais, bool $sinal = false): string
    {
        return self::brlCentavos(self::centavos($reais), $sinal);
    }

    /** Mesmo que brl(), recebendo centavos. */
    public static function brlCentavos(int $centavos, bool $sinal = false): string
    {
        $absoluto = abs($centavos);
        $resto = $absoluto % 100;
        $texto = 'R$ '.number_format(intdiv($absoluto, 100), 0, ',', '.')
            .($resto ? ','.str_pad((string) $resto, 2, '0', STR_PAD_LEFT) : '');

        return match (true) {
            $centavos < 0 => '−'.$texto,
            $sinal && $centavos > 0 => '+'.$texto,
            default => $texto,
        };
    }
}
