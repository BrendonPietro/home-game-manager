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

    /**
     * ": entregar 110.000 fichas (10.000 do time chip, 20.000 do pagamento antecipado, 40.000 de Taxa Comida)", ou
     * "" sem fichas. Os opcionais com fichas que foram junto vêm por nome.
     *
     * @param  list<array{0: string, 1: int}>  $opcionais  [nome, fichas]
     */
    public static function entregar(int $total, int $timeChip = 0, int $antecipado = 0, array $opcionais = []): string
    {
        if ($total <= 0) {
            return '';
        }

        $extras = array_filter([
            $timeChip > 0 ? self::numero($timeChip).' do time chip' : null,
            $antecipado > 0 ? self::numero($antecipado).' do pagamento antecipado' : null,
            ...array_map(fn (array $opcional): ?string => $opcional[1] > 0 ? self::numero($opcional[1]).' de '.$opcional[0] : null, $opcionais),
        ]);

        return ': entregar '.self::quantas($total).($extras ? ' ('.implode(', ', $extras).')' : '');
    }
}
