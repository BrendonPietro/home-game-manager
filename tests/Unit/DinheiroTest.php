<?php

namespace Tests\Unit;

use App\Support\Dinheiro;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DinheiroTest extends TestCase
{
    public function test_centavos(): void
    {
        $this->assertSame(189000, Dinheiro::centavos('1890.00'));
        $this->assertSame(1250, Dinheiro::centavos('12.5'));
        $this->assertSame(-20000, Dinheiro::centavos('-200'));
        $this->assertSame(10000, Dinheiro::centavos(100));
        $this->assertSame(1999, Dinheiro::centavos(19.99));
        $this->assertSame(0, Dinheiro::centavos(null));
        $this->assertSame(0, Dinheiro::centavos(''));
    }

    public function test_soma_sem_erro_de_float(): void
    {
        // 0.1 + 0.2 em float dá 0.30000000000000004
        $this->assertSame('0.30', Dinheiro::decimal(Dinheiro::centavos('0.10') + Dinheiro::centavos('0.20')));
    }

    public function test_decimal(): void
    {
        $this->assertSame('1890.00', Dinheiro::decimal(189000));
        $this->assertSame('0.05', Dinheiro::decimal(5));
        $this->assertSame('-200.00', Dinheiro::decimal(-20000));
    }

    public function test_brl(): void
    {
        $this->assertSame('R$ 1.890', Dinheiro::brl('1890.00'));
        $this->assertSame('R$ 12,50', Dinheiro::brl('12.50'));
        $this->assertSame('R$ 1.234.567,05', Dinheiro::brl('1234567.05'));
        $this->assertSame('R$ 0', Dinheiro::brl(null));
        $this->assertSame('−R$ 200', Dinheiro::brl('-200'));
    }

    public function test_brl_com_sinal(): void
    {
        $this->assertSame('+R$ 795', Dinheiro::brl('795.00', true));
        $this->assertSame('−R$ 200', Dinheiro::brl('-200.00', true));
        $this->assertSame('R$ 0', Dinheiro::brl('0', true));
    }

    public function test_recusa_texto_invalido(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dinheiro::centavos('1.890,00');
    }
}
