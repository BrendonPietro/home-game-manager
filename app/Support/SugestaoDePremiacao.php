<?php

namespace App\Support;

/**
 * Sugestões de divisão do prize pool na premiação manual do jogo, pelo número de lugares premiados.
 * Cada posição pesa uma fração da anterior: na **concentrada** metade (o 1º leva bem mais que o resto),
 * na **equilibrada** três quartos.
 *
 * Os percentuais saem em múltiplos de 5 (como as entradas e os prêmios, fica fácil de ver e de dividir),
 * com pelo menos 5% por posição e sempre somando 100. Com mais de 20 posições, 5% não cabe: vai de 1 em 1.
 * As sobras do arredondamento vão para as maiores frações; o que falta para o mínimo sai do 1º.
 */
class SugestaoDePremiacao
{
    public const CONCENTRADA = 0.5;

    public const EQUILIBRADA = 0.75;

    /** Passo preferido dos percentuais. */
    public const PASSO = 5;

    /** @return list<int> do 1º ao último */
    public static function concentrada(int $lugares): array
    {
        return self::dividir($lugares, self::CONCENTRADA);
    }

    /** @return list<int> do 1º ao último */
    public static function equilibrada(int $lugares): array
    {
        return self::dividir($lugares, self::EQUILIBRADA);
    }

    /** "55 / 30 / 15" */
    public static function texto(array $percentuais): string
    {
        return implode(' / ', $percentuais);
    }

    /** @return list<int> */
    public static function dividir(int $lugares, float $razao): array
    {
        if ($lugares < 1 || $lugares > 100) {
            return [];
        }

        $passo = $lugares * self::PASSO <= 100 ? self::PASSO : 1;
        $partes = intdiv(100, $passo);

        // Cada posição começa com o mínimo (um passo); o resto se divide pelos pesos.
        $resto = $partes - $lugares;
        $pesos = array_map(fn (int $i): float => $razao ** $i, range(0, $lugares - 1));
        $total = array_sum($pesos);
        $exatos = array_map(fn (float $peso): float => $peso / $total * $resto, $pesos);
        $inteiros = array_map(fn (float $exato): int => (int) floor($exato), $exatos);

        // Maior resto: as partes que sobram do arredondamento vão para as maiores frações.
        $sobras = array_map(fn (float $exato, int $inteiro): float => $exato - $inteiro, $exatos, $inteiros);
        arsort($sobras);
        foreach (array_slice(array_keys($sobras), 0, $resto - array_sum($inteiros)) as $posicao) {
            $inteiros[$posicao]++;
        }

        $inteiros = array_map(fn (int $parte): int => $parte + 1, $inteiros);

        // O arredondamento pode inverter dois vizinhos quase iguais: o 1º sempre leva mais (ou igual).
        rsort($inteiros);

        return array_map(fn (int $parte): int => $parte * $passo, $inteiros);
    }
}
