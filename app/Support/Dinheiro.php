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
    /** R$ 1 em centavos. */
    public const REAL = 100;

    /** Centavos → real inteiro mais próximo (meio real para cima): 12350 → 12400, 12349 → 12300. */
    public static function arredondarParaReal(int $centavos): int
    {
        return intdiv($centavos + ($centavos >= 0 ? 50 : -50), self::REAL) * self::REAL;
    }

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

    /**
     * Percentual de um valor em centavos, arredondando meio centavo para cima.
     * Ex.: percentual(10100, '7.5') → 758 (7,5% de R$ 101 = R$ 7,575).
     */
    public static function percentual(int $centavos, string|int|float $percentual): int
    {
        // Percentual também vira inteiro (centésimos de ponto): 7.5 → 750.
        $produto = $centavos * self::centavos($percentual);

        return intdiv($produto + ($produto >= 0 ? 5000 : -5000), 10000);
    }

    /**
     * Quantidade × valor unitário com até 4 casas (valor da ficha), em centavos, meio centavo para cima.
     * Ex.: multiplicar(25000, '0.0100') → 25000 (25 mil fichas de R$ 0,01 = R$ 250).
     */
    public static function multiplicar(int $quantidade, string|int $valorUnitario): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,4}))?$/', trim((string) $valorUnitario), $partes)) {
            throw new InvalidArgumentException("Valor unitário inválido: [{$valorUnitario}]");
        }

        // Décimos de milésimo de real: 0.0100 → 100.
        $unitario = (int) $partes[1] * 10000 + (int) str_pad($partes[2] ?? '0', 4, '0');

        return intdiv($quantidade * $unitario + 50, 100);
    }

    /**
     * Valor digitado num campo (jeito brasileiro) → centavos, ou null se não der para ler.
     * Aceita '100', '100,50', '1.234,50', 'R$ 1.234' e também '100.50'.
     */
    public static function lerTexto(?string $texto): ?int
    {
        $texto = preg_replace('/[\s\x{00A0}]+|R\$/u', '', (string) $texto);

        if (str_contains($texto, ',')) {
            $texto = str_replace(['.', ','], ['', '.'], $texto);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto)) {
            $texto = str_replace('.', '', $texto); // '1.500' = mil e quinhentos
        }

        return preg_match('/^\d+(\.\d{1,2})?$/', $texto) ? self::centavos($texto) : null;
    }

    /** Centavos → texto para um campo de valor ('1.234,50', '100'), o inverso de lerTexto(). */
    public static function paraTexto(int $centavos): string
    {
        return substr(self::brlCentavos(abs($centavos)), strlen('R$ '));
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
