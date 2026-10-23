<?php

namespace App\Support;

/**
 * Onde fica cada assento na mesa oval, em % do contêiner: lugares espaçados pelo comprimento de
 * arco (em proporção real), começando embaixo pelo dealer (assento 0). Mesma conta do RHG.pokerTable
 * do design system e do resources/js/site/mesa.js, que refaz isto no navegador ao girar a mesa.
 */
final class PosicoesDaMesa
{
    private const AMOSTRAS = 720;

    /**
     * @param  float  $ar  proporção largura/altura da mesa (--ar)
     * @param  float  $rx  raio horizontal em % (assentos sobre a borda)
     * @param  float  $ry  raio vertical em %
     * @return array<int, array{0: float, 1: float}> assento => [left %, top %]; 0 é o dealer
     */
    public static function calcular(int $lugares, float $ar = 1.7, float $rx = 42, float $ry = 38, int $rotacao = 0): array
    {
        $pontos = [];
        $comprimento = [0.0];

        for ($s = 0; $s <= self::AMOSTRAS; $s++) {
            $t = deg2rad(90 + $s * 360 / self::AMOSTRAS);
            $pontos[$s] = [$rx * cos($t), $ry * sin($t)];

            if ($s > 0) {
                $dx = ($pontos[$s][0] - $pontos[$s - 1][0]) * $ar;
                $dy = $pontos[$s][1] - $pontos[$s - 1][1];
                $comprimento[$s] = $comprimento[$s - 1] + sqrt($dx * $dx + $dy * $dy);
            }
        }

        $total = $comprimento[self::AMOSTRAS];
        $assentos = $lugares + 1;
        $posicoes = [];

        for ($k = 0; $k < $assentos; $k++) {
            $fracao = fmod(fmod($k / $assentos + $rotacao / 360, 1) + 1, 1);
            $alvo = $fracao * $total;
            $i = 0;

            while ($i < self::AMOSTRAS && $comprimento[$i] < $alvo) {
                $i++;
            }

            $posicoes[$k] = [round(50 + $pontos[$i][0], 2), round(50 + $pontos[$i][1], 2)];
        }

        return $posicoes;
    }
}
