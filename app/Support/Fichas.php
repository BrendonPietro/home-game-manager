<?php

namespace App\Support;

/** Texto das fichas que o caixa entrega: o do lançamento, o de quem senta e o do pagamento antecipado. */
final class Fichas
{
    /** 10000 → "10.000 fichas" */
    public static function quantas(int $quantas): string
    {
        return self::numero($quantas).($quantas === 1 ? ' ficha' : ' fichas');
    }

    /** 10000 → "10.000" */
    public static function numero(int $quantas): string
    {
        return number_format($quantas, 0, ',', '.');
    }

    /** ": entregar 70.000 fichas (10.000 do time chip, 20.000 do pagamento antecipado)", ou "" sem fichas. */
    public static function entregar(int $total, int $timeChip = 0, int $antecipado = 0): string
    {
        if ($total <= 0) {
            return '';
        }

        $extras = array_filter([
            $timeChip > 0 ? self::numero($timeChip).' do time chip' : null,
            $antecipado > 0 ? self::numero($antecipado).' do pagamento antecipado' : null,
        ]);

        return ': entregar '.self::quantas($total).($extras ? ' ('.implode(', ', $extras).')' : '');
    }
}
