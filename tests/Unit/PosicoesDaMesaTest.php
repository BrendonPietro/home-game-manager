<?php

namespace Tests\Unit;

use App\Support\PosicoesDaMesa;
use PHPUnit\Framework\TestCase;

class PosicoesDaMesaTest extends TestCase
{
    public function test_dealer_embaixo_no_centro_e_um_lugar_por_assento(): void
    {
        $posicoes = PosicoesDaMesa::calcular(9);

        $this->assertCount(10, $posicoes); // dealer + 9 lugares
        $this->assertEqualsWithDelta([50, 88], $posicoes[0], 0.01); // centro, raio vertical 38 abaixo
    }

    public function test_assentos_espelhados_dos_dois_lados(): void
    {
        $posicoes = PosicoesDaMesa::calcular(9);

        // Lugar 1 e lugar 9 ficam um de cada lado do dealer, na mesma altura.
        $this->assertEqualsWithDelta(100 - $posicoes[1][0], $posicoes[9][0], 0.5);
        $this->assertEqualsWithDelta($posicoes[1][1], $posicoes[9][1], 0.5);
        $this->assertLessThan(50, $posicoes[1][0]); // sentido horário: o 1 vem à esquerda do dealer
    }

    public function test_girar_meia_volta_leva_o_dealer_para_cima(): void
    {
        // A oval é amostrada a cada 0,5°: fora do ponto de partida, a posição pode cair na amostra vizinha.
        $this->assertEqualsWithDelta([50, 12], PosicoesDaMesa::calcular(9, rotacao: 180)[0], 0.5);
    }
}
