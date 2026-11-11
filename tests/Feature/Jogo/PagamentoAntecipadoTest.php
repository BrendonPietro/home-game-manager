<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\MarcarPagamentoAntecipado;
use App\Actions\Jogo\RegistrarEntrada;
use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Enums\TipoItem;
use App\Livewire\Jogo\AcoesJogador;
use App\Livewire\Jogo\AoVivo;
use App\Livewire\Jogo\Jogadores;
use App\Livewire\Jogo\Resumo;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use App\Services\Jogo\Entradas;
use App\Support\Fichas;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/**
 * Bônus do pagamento antecipado (20.000 fichas): quem está marcado como "pagou antes" ganha as fichas
 * junto do buy-in, ao sentar ou na hora, se já sentou. Quem marca é o Admin (permissão `pagamentos.antecipado`).
 */
class PagamentoAntecipadoTest extends TestCase
{
    use MontaNoite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNoite();
        $this->jogo->update(['antecipado_fichas' => 20000]);
    }

    private function marcar(Participante $participante, bool $pagou = true, ?Usuario $quem = null): Participante
    {
        return app(MarcarPagamentoAntecipado::class)($participante, $pagou, $quem ?? $this->admin);
    }

    /** Confirmado, ainda sem lugar. */
    private function confirmado(string $nome): Participante
    {
        return $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => $nome, 'apelido' => null])->id]);
    }

    public function test_quem_pagou_antes_ganha_o_bonus_no_buyin_e_as_fichas_se_somam(): void
    {
        $ana = $this->marcar($this->jogador('Ana'));
        $beto = $this->jogador('Beto');

        $buyin = $this->entrar($ana, TipoItem::Buyin);
        $this->assertSame([20000, 20000], [$buyin->fichas, $buyin->antecipado]);
        $this->assertSame(': entregar 40.000 fichas (20.000 do pagamento antecipado)', RegistrarEntrada::aEntregar($buyin));
        $this->assertSame(0, $this->entrar($beto, TipoItem::Buyin)->antecipado);

        // O rebuy não ganha o bônus.
        $this->assertSame(0, $this->entrar($ana, TipoItem::Rebuy)->antecipado);

        $porJogador = app(Entradas::class)->porJogador($this->jogo);
        $this->assertSame([60000, 20000], [$porJogador[$ana->id]->fichas, $porJogador[$ana->id]->antecipado]);
        $this->assertSame([20000, 0], [$porJogador[$beto->id]->fichas, $porJogador[$beto->id]->antecipado]);
    }

    public function test_junto_com_o_time_chip(): void
    {
        $this->jogo->update(['time_chip_fichas' => 10000, 'time_chip_ate_nivel' => 1]);
        $ana = $this->marcar($this->jogador('Ana'));

        $this->assertSame(': entregar 50.000 fichas (10.000 do time chip, 20.000 do pagamento antecipado)', RegistrarEntrada::aEntregar($this->entrar($ana, TipoItem::Buyin)));
    }

    public function test_marcar_depois_de_sentar_poe_o_bonus_no_buyin_e_desmarcar_tira(): void
    {
        $ana = $this->jogador('Ana');
        $buyin = $this->entrar($ana, TipoItem::Buyin);

        $ana = $this->marcar($ana);
        $this->assertSame(20000, $buyin->fresh()->antecipado);
        $this->assertSame('Ana pagou antes: entregar 20.000 fichas a mais.', MarcarPagamentoAntecipado::aviso($ana));

        $ana = $this->marcar($ana, false);
        $this->assertSame(0, $buyin->fresh()->antecipado);
        $this->assertSame('Ana sem o pagamento antecipado: o buy-in perde as 20.000 fichas.', MarcarPagamentoAntecipado::aviso($ana));

        // Fica no histórico; marcar de novo o que já está marcado não repete.
        $this->marcar($ana, false);
        $this->assertSame(
            ['Ana pagou antes: ganha 20.000 fichas no buy-in já lançado', 'Ana sem o pagamento antecipado: o buy-in perde as 20.000 fichas'],
            $this->jogo->alteracoes()->where('tipo', TipoAlteracao::PagamentoAntecipado)->reorder('id')->pluck('descricao')->all(),
        );
    }

    public function test_regras(): void
    {
        $ana = $this->jogador('Ana');

        // Operador não marca.
        $this->expectExceptionObject(new AuthorizationException('Você não tem permissão para marcar o pagamento antecipado.'));
        $this->marcar($ana, quem: $this->operador);
    }

    public function test_so_no_torneio_com_bonus_agendado_ou_em_andamento(): void
    {
        $ana = $this->jogador('Ana');

        $this->jogo->update(['antecipado_fichas' => null]);
        $this->assertRegra('Este torneio não tem bônus do pagamento antecipado: defina as fichas no cadastro do jogo, aba Blinds.', fn () => $this->marcar($ana));

        $this->jogo->update(['antecipado_fichas' => 20000]);
        $this->jogo->forceFill(['status' => StatusJogo::Finalizado])->save();
        $this->assertRegra('O pagamento antecipado se marca com o jogo agendado ou em andamento.', fn () => $this->marcar($ana));

        // Cash game não tem bônus (as fichas valem dinheiro).
        $cash = Jogo::factory()->cashGame()->create(['antecipado_fichas' => 20000]);
        $this->assertFalse($cash->temBonusAntecipado());
    }

    public function test_quem_senta_aqui_mostra_quem_pagou_antes_e_o_aviso_diz_quanto_entregar(): void
    {
        $this->withoutVite();
        $this->actingAs($this->admin, 'web');
        $ana = $this->marcar($this->confirmado('Ana'));
        $this->confirmado('Beto');

        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->call('clicarAssento', $this->mesa->id, 5)
            ->assertSee(['Pagou antes · +20.000', 'entregar 20.000 fichas; quem pagou antes, mais 20.000'])
            ->call('sentarNoLugar', $ana->id)
            ->assertSet('aviso', 'Ana sentou na Mesa 01 · Lugar 5, pagou antes: entregar 40.000 fichas (20.000 do pagamento antecipado).');

        $this->assertSame(20000, $ana->entradas()->sole()->antecipado);

        // Quem não pagou antes: só as fichas do buy-in.
        $beto = $this->jogo->participantes()->whereHas('usuario', fn ($q) => $q->where('nome', 'Beto'))->sole();
        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->call('clicarAssento', $this->mesa->id, 6)
            ->call('sentarNoLugar', $beto->id)
            ->assertSet('aviso', 'Beto sentou na Mesa 01 · Lugar 6: entregar 20.000 fichas.');
    }

    public function test_painel_do_jogador_marca_e_mostra_as_fichas(): void
    {
        $ana = $this->jogador('Ana');
        $this->entrar($ana, TipoItem::Buyin);
        $this->actingAs($this->admin, 'web');

        Livewire::test(AcoesJogador::class, ['jogo' => $this->jogo, 'participanteId' => $ana->id])
            ->assertSee(['Pagamento antecipado', 'Quem pagou antes ganha 20.000 fichas', 'Marcar'])
            ->call('alternarAntecipado')
            ->assertSet('aviso', 'Ana pagou antes: entregar 20.000 fichas a mais.')
            ->assertDispatched('caixa-mudou')
            ->assertSee(['Pagou antes', '+20.000 fichas no buy-in', 'Desmarcar', '40.000', 'buy-in 20.000 · pagamento antecipado 20.000']);

        Livewire::test(Resumo::class, ['jogo' => $this->jogo])
            ->assertSee(['Pagou antes', 'Com 20.000 do pagamento antecipado']);

        // O Operador vê, mas não marca.
        $this->actingAs($this->operador, 'web');
        Livewire::test(AcoesJogador::class, ['jogo' => $this->jogo, 'participanteId' => $ana->id])
            ->assertSee('Pagou antes')
            ->assertDontSee('Desmarcar');
    }

    public function test_lista_da_noite_marca_quem_pagou_antes(): void
    {
        $jogo = Jogo::factory()->agendado()->comEntradasPadrao()->create(['antecipado_fichas' => 20000]);
        $ana = $jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => 'Ana', 'apelido' => null])->id]);
        $this->actingAs($this->admin, 'web');

        Livewire::test(Jogadores::class, ['jogo' => $jogo])
            ->assertSee('Quem pagou antes ganha 20.000 fichas a mais ao sentar')
            ->call('alternarAntecipado', $ana->id)
            ->assertSet('erro', null);

        $this->assertTrue($ana->fresh()->pagou_antes);
    }

    public function test_texto_das_fichas(): void
    {
        $this->assertSame('', Fichas::entregar(0, 0, 20000));
        $this->assertSame(': entregar 1 ficha', Fichas::entregar(1));
        $this->assertSame(': entregar 30.000 fichas (10.000 do time chip)', Fichas::entregar(30000, 10000));
    }
}
