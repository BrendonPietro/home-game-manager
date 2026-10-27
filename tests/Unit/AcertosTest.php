<?php

namespace Tests\Unit;

use App\Services\Jogo\Acertos;
use App\Services\Jogo\Saldos;
use App\Services\Jogo\Transferencia;
use PHPUnit\Framework\TestCase;

class AcertosTest extends TestCase
{
    /** @return list<array{0: ?int, 1: ?int, 2: int}> */
    private function dividir(array $saldos): array
    {
        return array_map(
            fn (Transferencia $t) => [$t->de, $t->para, $t->valor],
            (new Acertos(new Saldos))->dividir($saldos),
        );
    }

    public function test_quem_fecha_exato_paga_direto(): void
    {
        // 3 recebe 100: ninguém sozinho tem 100, mas 1 + 2 fecham.
        $this->assertSame(
            [[1, 3, 60], [2, 3, 40], [4, 5, 50]],
            $this->dividir([1 => 60, 2 => 40, 3 => -100, 4 => 50, 5 => -50]),
        );
    }

    public function test_sem_combinacao_exata_o_maior_paga_o_maior(): void
    {
        $this->assertSame(
            [[1, 3, 70], [2, 4, 40], [2, 3, 10]],
            $this->dividir([1 => 70, 2 => 50, 3 => -80, 4 => -40]),
        );
    }

    public function test_caixa_entra_como_participante_nulo(): void
    {
        $this->assertSame([[1, null, 300]], $this->dividir([1 => 300, 'casa' => -300]));
        $this->assertSame([[null, 2, 120]], $this->dividir(['casa' => 120, 2 => -120]));
        $this->assertSame([], $this->dividir([]));
    }

    public function test_cada_um_fecha_o_proprio_saldo(): void
    {
        $saldos = [1 => 25000, 2 => 20000, 3 => 20000, 4 => 10000, 5 => -79500, 6 => -31700, 7 => -12800, 8 => 15000, 'casa' => 14000];
        $saldos['casa'] -= array_sum($saldos); // a soma é zero

        $liquido = array_fill_keys(array_keys($saldos), 0);

        foreach ((new Acertos(new Saldos))->dividir($saldos) as $t) {
            $liquido[$t->de ?? 'casa'] += $t->valor;
            $liquido[$t->para ?? 'casa'] -= $t->valor;
        }

        $this->assertSame($saldos, $liquido);
    }
}
