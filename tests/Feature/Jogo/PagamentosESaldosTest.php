<?php

namespace Tests\Feature\Jogo;

use App\Enums\DirecaoPagamento;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Services\Jogo\Saldos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class PagamentosESaldosTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNoite();
    }

    public function test_pagamentos_parciais_e_saldo(): void
    {
        $joao = $this->jogador('João');
        $lucas = $this->jogador('Lucas');
        $this->entrar($joao, TipoItem::Buyin);
        $this->entrar($joao, TipoItem::Rebuy);

        $uuid = (string) Str::uuid();
        $pix = $this->pagar($joao, 15000, uuid: $uuid);
        $this->assertTrue($this->pagar($joao, 15000, uuid: $uuid)->is($pix)); // toque duplo
        $this->pagar($joao, 5050, DirecaoPagamento::JogadorRecebeu);

        $saldo = app(Saldos::class)->doParticipante($joao);

        // Investido 200 − pago 150 + recebido 50,50 = deve R$ 100,50.
        $this->assertSame([20000, 0, 15000, 5050, 10050], [$saldo->investido, $saldo->premio, $saldo->pago, $saldo->recebido, $saldo->saldo]);
        $this->assertSame('150.00', $pix->valor);

        $todos = app(Saldos::class)->doJogo($this->jogo);
        $this->assertSame([10050, 0], [$todos[$joao->id]->saldo, $todos[$lucas->id]->saldo]);
        $this->assertTrue($todos[$lucas->id]->quitado());
    }

    public function test_valor_e_status_do_jogo(): void
    {
        $joao = $this->jogador('João');

        $this->assertRegra('Informe um valor maior que zero.', fn () => $this->pagar($joao, 0));

        // Pendência paga depois de finalizar.
        $this->jogo->forceFill(['status' => StatusJogo::Finalizado])->save();
        $this->pagar($joao, 1000);

        $this->jogo->forceFill(['status' => StatusJogo::Cancelado])->save();
        $this->assertRegra('Pagamentos só com o jogo agendado, em andamento ou finalizado.', fn () => $this->pagar($joao, 1000));
    }
}
