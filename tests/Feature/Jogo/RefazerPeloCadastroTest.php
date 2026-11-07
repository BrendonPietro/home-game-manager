<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\RefazerPeloCadastro;
use App\Actions\Jogo\RegistrarCashOut;
use App\Enums\TipoAlteracao;
use App\Enums\TipoItem;
use App\Models\Jogo;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\Saldos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MontaNoite;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

/** Jogo em andamento mudado no painel: entradas, cash-out, prêmios e posições seguem o cadastro novo. */
class RefazerPeloCadastroTest extends TestCase
{
    use MontaNoite, RefreshDatabase, UsaPainel;

    public function test_refaz_as_entradas_e_o_que_cada_um_deve(): void
    {
        $conta = $this->entrarNoPainel('Admin');
        $this->montarNoite();
        [$ana, $beto] = [$this->jogador('Ana'), $this->jogador('Beto')];
        $this->entrar($ana, TipoItem::Buyin);
        $this->entrar($beto, TipoItem::Buyin);
        $this->entrar($ana, TipoItem::Rebuy);
        $this->pagar($ana, 10000);

        // O painel salvou o rebuy a R$ 150, com 75% para a premiação.
        $antes = app(Calculadora::class)->doJogo($this->jogo);
        $this->item(TipoItem::Rebuy)->update(['valor' => 150, 'percentual_premiacao' => 75]);

        $alteracao = app(RefazerPeloCadastro::class)($this->jogo, $conta, $antes);

        $rebuy = $this->jogo->entradas()->where('tipo', TipoItem::Rebuy)->sole();
        $this->assertSame(['150.00', '75.00'], [$rebuy->valor, $rebuy->percentual_premiacao]);
        $this->assertSame(['100.00'], $this->jogo->entradas()->where('tipo', TipoItem::Buyin)->pluck('valor')->unique()->values()->all());

        // Ana: 100 + 150 investidos, pagou 100: deve 150. Prize pool: 100 + 100 + 75% de 150 = 312,50, desce para o real.
        $this->assertSame(15000, app(Saldos::class)->doParticipante($ana->fresh())->saldo);
        $this->assertSame(35000, app(Calculadora::class)->doJogo($this->jogo)->arrecadacao);

        $this->assertSame(TipoAlteracao::CadastroAlterado, $alteracao->tipo);
        $this->assertTrue($alteracao->tipo->correcao());
        $this->assertSame($conta->id, $alteracao->seguranca_id);
        $this->assertStringContainsString('Rebuy R$ 100 → R$ 150, premiação 100% → 75% (1 entrada)', $alteracao->descricao);
        $this->assertStringContainsString('prize pool R$ 300 → R$ 312', $alteracao->descricao);

        // Nada mudou de novo: não refaz nem registra.
        $this->assertNull(app(RefazerPeloCadastro::class)($this->jogo, $conta));
    }

    public function test_com_as_entradas_fechadas_regrava_premios_e_posicoes(): void
    {
        $conta = $this->entrarNoPainel('Admin');
        $this->montarNoite();
        $this->jogo->update(['metodo_premiacao' => 'manual']);
        $this->jogo->premiacoes()->createMany([['posicao' => 1, 'percentual' => 60], ['posicao' => 2, 'percentual' => 40]]);

        [$ana, $beto, $caio] = array_map(fn (string $nome) => $this->jogador($nome), ['Ana', 'Beto', 'Caio']);
        foreach ([$ana, $beto, $caio] as $jogador) {
            $this->entrar($jogador, TipoItem::Buyin);
        }

        app(AbrirIntervalo::class)($this->jogo, $this->admin);
        app(FecharEntradas::class)($this->jogo, $this->admin);
        $this->eliminar($caio);
        $this->eliminar($beto); // 2º: R$ 120 de R$ 300; a Ana fica em 1º com R$ 180

        $this->assertSame(['120.00', '180.00'], [$beto->fresh()->premio, $ana->fresh()->premio]);

        // Buy-in passa a R$ 200: prize pool R$ 600, prêmios 360 / 240.
        $this->item(TipoItem::Buyin)->update(['valor' => 200]);
        $alteracao = app(RefazerPeloCadastro::class)($this->jogo, $conta);

        $this->assertSame(['360.00', '240.00'], $this->jogo->premiacoes()->orderBy('posicao')->pluck('valor')->all());
        $this->assertSame([1, '360.00'], [$ana->fresh()->posicao_final, $ana->fresh()->premio]);
        $this->assertSame([2, '240.00'], [$beto->fresh()->posicao_final, $beto->fresh()->premio]);
        $this->assertSame([3, null], [$caio->fresh()->posicao_final, $caio->fresh()->premio]);
        $this->assertStringContainsString('Buy-in R$ 100 → R$ 200 (3 entradas); prêmios R$ 360 / R$ 240', $alteracao->descricao);
    }

    public function test_cash_game_refaz_o_cash_out_pelo_valor_da_ficha(): void
    {
        $conta = $this->entrarNoPainel('Admin');
        $this->montarNoite(Jogo::factory()->cashGame()->emAndamento()->comEntradasPadrao()->create());
        $ana = $this->jogador('Ana');
        $this->entrar($ana, TipoItem::Buyin);
        app(RegistrarCashOut::class)($ana, 15000, $this->admin); // 15.000 × R$ 0,01 = R$ 150

        $this->jogo->update(['valor_ficha' => '0.0200']);
        $alteracao = app(RefazerPeloCadastro::class)($this->jogo, $conta);

        $this->assertSame('300.00', $ana->fresh()->cash_out);
        $this->assertStringContainsString('cash-out refeito com a ficha a R$ 0,02 (1 jogador)', $alteracao->descricao);
    }

    public function test_so_com_a_permissao_e_so_em_andamento(): void
    {
        $this->montarNoite();

        $this->expectException(AuthorizationException::class);
        app(RefazerPeloCadastro::class)($this->jogo, $this->entrarNoPainel('Geral'));
    }
}
