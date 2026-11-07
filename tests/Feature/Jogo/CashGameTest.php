<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\RegistrarCashOut;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Saldos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class CashGameTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ficha de R$ 0,01.
        $this->montarNoite(Jogo::factory()->cashGame()->emAndamento()->comEntradasPadrao()->create());
    }

    private function cashOut(Participante $participante, int $fichas): void
    {
        app(RegistrarCashOut::class)($participante, $fichas, $this->operador);
    }

    public function test_entradas_do_cash_game(): void
    {
        $joao = $this->jogador('João');
        $this->entrar($joao, TipoItem::Buyin);
        $this->entrar($joao, TipoItem::Rebuy); // recompra a qualquer hora

        $this->assertRegra('João já tem buy-in. Para mais fichas, recompra.', fn () => $this->entrar($joao, TipoItem::Buyin));
        $this->assertRegra('Cash game não tem add-on.', fn () => $this->entrar($joao, TipoItem::Addon));
        $this->assertRegra('Cash game não tem eliminação: registre o cash-out.', fn () => $this->eliminar($joao));
        $this->assertRegra('Cash game não tem fases.', fn () => app(AbrirIntervalo::class)($this->jogo, $this->admin));
    }

    public function test_cash_out_e_saldo(): void
    {
        $joao = $this->jogador('João');
        $this->entrar($joao, TipoItem::Buyin);
        $this->entrar($joao, TipoItem::Rebuy);

        $this->cashOut($joao, 32550);

        $joao->refresh();
        $this->assertSame([32550, '325.50', StatusParticipante::Saiu], [$joao->fichas_finais, $joao->cash_out, $joao->status]);
        $this->assertSame([$this->mesa->id, 1], [$joao->jogo_mesa_id, $joao->lugar]);

        // Registrar de novo corrige.
        $this->cashOut($joao, 30000);
        $saldo = app(Saldos::class)->doParticipante($joao->fresh());

        // Investiu 200, saiu com 300: o caixa deve R$ 100 a ele.
        $this->assertSame([20000, 30000, -10000, 10000], [$saldo->investido, $saldo->premio, $saldo->saldo, $saldo->resultado()]);
        $this->assertRegra('João já saiu do jogo.', fn () => $this->entrar($joao, TipoItem::Rebuy));
        $this->assertRegra('Zeca não tem buy-in.', fn () => $this->cashOut($this->jogador('Zeca'), 100));
    }

    public function test_torneio_nao_tem_cash_out(): void
    {
        $torneio = Jogo::factory()->emAndamento()->create();
        $participante = $torneio->participantes()->create(['usuario_id' => Usuario::factory()->create()->id]);

        $this->assertRegra('Cash-out é do cash game: no torneio, registre a eliminação.', fn () => $this->cashOut($participante, 100));
    }
}
