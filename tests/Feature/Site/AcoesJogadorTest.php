<?php

namespace Tests\Feature\Site;

use App\Enums\DestinoItem;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Livewire\Jogo\AcoesJogador;
use App\Livewire\Jogo\AoVivo;
use App\Models\Jogo;
use App\Models\Participante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class AcoesJogadorTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    private Participante $joao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->montarNoite();
        $this->actingAs($this->admin, 'web');
        $this->joao = $this->jogador('João');
    }

    private function painel(?Participante $participante = null, ?Jogo $jogo = null): Testable
    {
        return Livewire::test(AcoesJogador::class, [
            'jogo' => $jogo ?? $this->jogo,
            'participanteId' => ($participante ?? $this->joao)->id,
        ]);
    }

    public function test_painel_mostra_o_que_comprou_a_conta_e_o_historico(): void
    {
        $this->entrar($this->jogador('Lucas'), TipoItem::Buyin);
        $this->entrar($this->joao, TipoItem::Buyin);
        $this->entrar($this->joao, TipoItem::Rebuy);
        $this->pagar($this->joao, 15000);

        $this->painel()
            ->assertSeeInOrder(['rh-jogador__nome', 'João', 'Mesa 01 · Lugar 1'], false)
            ->assertSeeInOrder(['Buy-in', '20.000 fichas', 'R$ 100', 'Rebuys · 1', 'R$ 100'])
            ->assertSeeInOrder(['Investido', 'R$ 200', 'Pago', 'R$ 150', 'Deve ao caixa', 'R$ 50'])
            ->assertSeeInOrder(['Histórico', 'Pagou R$ 150 (pix)', 'Rebuy · R$ 100', 'Buy-in · R$ 100'])
            ->assertSee('Desfazer')
            ->assertSee('REGISTRAR ELIMINAÇÃO')
            ->assertSee('MUDAR DE LUGAR');
    }

    public function test_botoes_seguem_a_fase(): void
    {
        $this->painel()
            ->assertSeeInOrder(['Buy-in', 'R$ 100', 'Rebuy', 'falta o buy-in', 'Add-on', 'no intervalo']);

        $this->entrar($this->joao, TipoItem::Buyin);

        // Único com buy-in: é o último em jogo, não tem como cair.
        $this->painel()
            ->assertSeeInOrder(['Buy-in', 'já feito', 'Rebuy', 'R$ 100'])
            ->assertDontSee('REGISTRAR ELIMINAÇÃO');
    }

    public function test_lancar_buyin_com_opcional_e_toque_duplo(): void
    {
        $churrasco = $this->jogo->itens()->create([
            'tipo' => TipoItem::Opcional, 'nome' => 'Churrasco', 'valor' => 30, 'compravel_com' => ['jogador'], 'destino' => DestinoItem::Fora,
        ]);

        $this->painel()
            ->call('abrirEntrada', 'buyin')
            ->assertSet('modal', 'entrada')
            ->assertSee('Novo buy-in · João')
            ->assertSee('Churrasco')
            ->set('opcionais', [(string) $churrasco->id])
            ->assertSeeInOrder(['Total do jogador passa de', 'R$ 0', 'R$ 130'])
            ->call('confirmarEntrada')
            ->assertSet('modal', null)
            ->assertSet('aviso', 'Buy-in registrado para João.')
            ->assertDispatched('caixa-mudou')
            ->call('confirmarEntrada'); // o mesmo uuid de novo

        $this->assertSame(2, $this->jogo->entradas()->count());
    }

    public function test_regra_aparece_no_modal(): void
    {
        $this->entrar($this->joao, TipoItem::Buyin);

        $this->painel()
            ->call('abrirEntrada', 'addon')
            ->call('confirmarEntrada')
            ->assertSet('modal', 'entrada')
            ->assertSee('Add-on só no intervalo, depois de encerrar os rebuys.')
            ->assertNotDispatched('caixa-mudou');
    }

    public function test_pagamento_sugere_o_saldo_e_le_o_valor_digitado(): void
    {
        $this->entrar($this->joao, TipoItem::Buyin);

        $this->painel()
            ->call('abrirPagamento')
            ->assertSet('direcao', 'jogador_pagou')
            ->assertSet('valor', '100')
            ->set('valor', 'abc')
            ->call('confirmarPagamento')
            ->assertSee('Valor inválido')
            ->set('valor', '60,50')
            ->set('forma', 'dinheiro')
            ->call('confirmarPagamento')
            ->assertSet('aviso', 'João pagou R$ 60,50 (dinheiro).')
            ->assertSeeInOrder(['Deve ao caixa', 'R$ 39,50']);
    }

    public function test_eliminacao_e_desfazer(): void
    {
        $this->entrar($this->joao, TipoItem::Buyin);
        $this->entrar($this->jogador('Lucas'), TipoItem::Buyin);

        $this->painel()
            ->call('abrirEliminacao')
            ->assertSee('Provisória: João pode voltar com rebuy até encerrar os rebuys.')
            ->call('confirmarEliminacao')
            ->assertSet('aviso', 'João eliminado: pode voltar com rebuy até encerrar os rebuys.')
            ->assertSee('Eliminado')
            ->assertDontSee('REGISTRAR ELIMINAÇÃO')
            ->call('abrirDesfazer')
            ->assertSee('João volta a jogar.')
            ->call('confirmarDesfazer')
            ->assertSee('Diga o motivo para desfazer.')
            ->set('motivo', 'Ainda tinha fichas')
            ->call('confirmarDesfazer')
            ->assertSet('aviso', 'Eliminação de João desfeita.');

        $this->assertSame(StatusParticipante::Jogando, $this->joao->fresh()->status);
    }

    public function test_operador_lanca_mas_nao_desfaz_nem_muda_lugar(): void
    {
        $this->entrar($this->joao, TipoItem::Buyin);
        $this->actingAs($this->operador, 'web');

        $this->painel()
            ->assertDontSee('Desfazer')
            ->assertDontSee('MUDAR DE LUGAR')
            ->assertSee('REGISTRAR PAGAMENTO')
            ->set('motivo', 'Errei')
            ->call('confirmarDesfazer')
            ->assertSet('erro', 'Só o Admin do site desfaz ações.');
    }

    public function test_cash_out_com_previa_do_valor(): void
    {
        $cash = Jogo::factory()->cashGame()->emAndamento()->comEntradasPadrao()->create();
        $ana = $cash->participantes()->create(['usuario_id' => $this->joao->usuario_id]);
        $ana->forceFill(['status' => StatusParticipante::Jogando])->save();
        $this->entrar($ana, $cash->itens()->where('tipo', TipoItem::Buyin)->first());

        $this->painel($ana, $cash)
            ->assertSee('Recompras')
            ->assertDontSee('Add-on')
            ->call('abrirCashOut')
            ->set('fichas', '32.550')
            ->assertSeeInOrder(['Cash-out', 'R$ 325,50', 'Resultado', '+R$ 225,50'])
            ->call('confirmarCashOut')
            ->assertSet('aviso', 'Cash-out de João: R$ 325,50.')
            ->assertSee('CORRIGIR CASH-OUT');
    }

    public function test_tela_ao_vivo_abre_o_painel_e_muda_o_lugar(): void
    {
        $lucas = $this->jogador('Lucas');

        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->call('clicarAssento', $this->mesa->id, 1)
            ->assertSet('selecionado', $this->joao->id)
            ->assertSeeLivewire(AcoesJogador::class)
            ->dispatch('mudar-lugar', participanteId: $this->joao->id)
            ->assertSet('selecionado', null)
            ->assertSet('movendo', $this->joao->id)
            ->assertSee('clique num lugar vazio para mover')
            ->call('clicarAssento', $this->mesa->id, 2) // troca com o Lucas
            ->assertSet('movendo', null);

        $this->assertSame([2, 1], [$this->joao->fresh()->lugar, $lucas->fresh()->lugar]);

        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->call('selecionar', $lucas->id)
            ->dispatch('fechar-jogador')
            ->assertSet('selecionado', null);
    }
}
