<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\MarcarOpcionalPagoAntes;
use App\Actions\Jogo\RegistrarEntrada;
use App\Enums\TipoAlteracao;
use App\Enums\TipoItem;
use App\Livewire\Jogo\AcoesJogador;
use App\Livewire\Jogo\AoVivo;
use App\Livewire\Jogo\Jogadores;
use App\Livewire\Jogo\Sorteio;
use App\Models\Entrada;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\Participante;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/**
 * Opcionais pagos antes (taxa da pizza): o opcional com "Pago antes" no cadastro e o jogador marcado (no painel
 * ou no site) levam o opcional junto do buy-in, com as fichas, ao sentar; já sentado, entra na hora.
 */
class OpcionaisPagosAntesTest extends TestCase
{
    use MontaNoite, RefreshDatabase;

    private Item $pizza;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNoite();
        $this->pizza = $this->opcional('Taxa Comida', 40, ['jogador'], 40000);
    }

    private function opcional(string $nome, int $valor, array $compravelCom, int $fichas, bool $pagoAntes = true, ?Jogo $jogo = null): Item
    {
        return ($jogo ?? $this->jogo)->itens()->create([
            'tipo' => TipoItem::Opcional,
            'nome' => $nome,
            'valor' => $valor,
            'fichas' => $fichas,
            'compravel_com' => $compravelCom,
            'percentual_premiacao' => 0,
            'pago_antes' => $pagoAntes,
        ]);
    }

    private function marcar(Participante $participante, ?Item $opcional = null, bool $pagou = true, ?Usuario $quem = null): string
    {
        return app(MarcarOpcionalPagoAntes::class)($participante, $opcional ?? $this->pizza, $pagou, $quem ?? $this->admin);
    }

    /** Confirmado, ainda sem lugar. */
    private function confirmado(string $nome, ?Jogo $jogo = null): Participante
    {
        return ($jogo ?? $this->jogo)->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => $nome, 'apelido' => null])->id]);
    }

    /** @return list<string> */
    private function junto(Entrada $entrada): array
    {
        return $entrada->opcionais()->validas()->with('item')->orderBy('id')->get()->pluck('item.nome')->all();
    }

    public function test_o_buyin_leva_os_opcionais_pagos_antes_com_as_fichas(): void
    {
        $fichas = $this->opcional('Opcional', 10, ['buyin', 'rebuy', 'addon'], 10000);
        $this->opcional('Churrasco', 30, ['jogador'], 0, pagoAntes: false);

        $ana = $this->jogador('Ana');
        $this->assertSame('Ana pagou Taxa Comida antes: entra junto do buy-in, com 40.000 fichas.', $this->marcar($ana));
        $this->marcar($ana, $fichas);

        $buyin = $this->entrar($ana, TipoItem::Buyin);
        $this->assertSame(['Taxa Comida', 'Opcional'], $this->junto($buyin));
        $this->assertSame(': entregar 70.000 fichas (40.000 de Taxa Comida, 10.000 de Opcional)', RegistrarEntrada::aEntregar($buyin));

        // Quem não está marcado entra só com o buy-in; o rebuy não leva o pago antes.
        $this->assertSame([], $this->junto($this->entrar($this->jogador('Beto'), TipoItem::Buyin)));
        $this->assertSame([], $this->junto($this->entrar($ana, TipoItem::Rebuy)));

        // Fica no histórico.
        $this->assertTrue($this->jogo->alteracoes()->where('tipo', TipoAlteracao::PagamentoAntecipado)
            ->where('descricao', 'Ana pagou Taxa Comida antes: entra junto do buy-in, com 40.000 fichas.')->exists());
    }

    public function test_desfazer_o_buyin_leva_junto_e_refazer_traz_de_novo(): void
    {
        $ana = $this->jogador('Ana');
        $this->marcar($ana);
        $this->entrar($this->jogador('Beto'), TipoItem::Buyin);

        $this->entrar($ana, TipoItem::Buyin);
        $this->assertSame('Buy-in de Ana desfeito.', $this->desfazer($ana));
        $this->assertSame(0, $ana->entradas()->validas()->count());

        $this->assertSame(['Taxa Comida'], $this->junto($this->entrar($ana, TipoItem::Buyin)));
    }

    public function test_marcar_quem_ja_sentou_lanca_na_hora_e_desmarcar_tira(): void
    {
        $ana = $this->jogador('Ana');
        $buyin = $this->entrar($ana, TipoItem::Buyin);

        $this->assertSame('Ana pagou Taxa Comida antes: entregar 40.000 fichas.', $this->marcar($ana));
        $junto = $buyin->opcionais()->validas()->sole();
        $this->assertSame([$this->pizza->id, $this->admin->id], [$junto->jogo_item_id, $junto->registrado_por]);

        $this->assertSame('Ana não pagou Taxa Comida antes: saiu do buy-in (recolher 40.000 fichas).', $this->marcar($ana, pagou: false));
        $this->assertSame('Não pagou antes', $junto->fresh()->motivo_cancelamento);
        $this->assertFalse($ana->opcionaisPagos()->exists());

        // Comprado na hora pelo cartão: marcar não lança de novo, e desmarcar não tira o que ele comprou.
        $naHora = $this->entrar($ana, $this->pizza);
        $this->assertSame('Ana pagou Taxa Comida antes (já tinha comprado na hora).', $this->marcar($ana));
        $this->assertSame('Ana não pagou Taxa Comida antes.', $this->marcar($ana, pagou: false));
        $this->assertNull($naHora->fresh()->cancelada_em);
        $this->assertSame(1, $ana->entradas()->validas()->where('jogo_item_id', $this->pizza->id)->count());
    }

    public function test_com_as_entradas_fechadas_nao_muda_o_buyin(): void
    {
        $ana = $this->jogador('Ana');
        $this->entrar($ana, TipoItem::Buyin);
        $this->entrar($this->jogador('Beto'), TipoItem::Buyin);
        app(AbrirIntervalo::class)($this->jogo, $this->admin);
        app(FecharEntradas::class)($this->jogo, $this->admin);

        $this->assertRegra('As entradas já foram fechadas: Taxa Comida não muda mais no buy-in de Ana.', fn () => $this->marcar($ana));
        $this->assertFalse($ana->opcionaisPagos()->exists());
    }

    public function test_regras_e_permissao(): void
    {
        $churrasco = $this->opcional('Churrasco', 30, ['jogador'], 0, pagoAntes: false);
        $ana = $this->jogador('Ana');

        $this->assertRegra('Churrasco não se paga antes: ligue "Pago antes" no opcional, no cadastro do jogo.', fn () => $this->marcar($ana, $churrasco));

        $this->marcar($ana);
        $this->assertSame('Ana já estava com Taxa Comida pago antes.', $this->marcar($ana));

        // Opcional que deixou de ser pago antes não vai mais junto (a marca fica, sem efeito).
        $this->pizza->update(['pago_antes' => false]);
        $this->assertSame([], $this->junto($this->entrar($ana, TipoItem::Buyin)));

        $this->expectException(AuthorizationException::class);
        $this->marcar($this->jogador('Beto'), $this->pizza, quem: $this->operador);
    }

    public function test_entrada_nao_fica_paga_antes(): void
    {
        $buyin = $this->item(TipoItem::Buyin);
        $buyin->update(['pago_antes' => true]);

        $this->assertFalse($buyin->fresh()->pago_antes);
    }

    public function test_lista_da_noite_marca_e_quem_senta_aqui_mostra(): void
    {
        $this->withoutVite();
        $this->actingAs($this->admin, 'web');

        $agendado = Jogo::factory()->agendado()->comEntradasPadrao()->create();
        $pizza = $this->opcional('Taxa Comida', 40, ['jogador'], 40000, jogo: $agendado);
        $bia = $this->confirmado('Bia', $agendado);

        Livewire::test(Jogadores::class, ['jogo' => $agendado])
            ->assertSee('Pago antes: entra junto do buy-in, com 40.000 fichas')
            ->call('alternarOpcionalPago', $bia->id, $pizza->id)
            ->assertSet('erro', null);

        $this->assertTrue($bia->opcionaisPagos()->whereKey($pizza->id)->exists());

        // Na noite: o Quem senta aqui mostra o selo e o aviso diz o que entregar.
        $ana = $this->confirmado('Ana');
        $this->marcar($ana);

        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->call('clicarAssento', $this->mesa->id, 5)
            ->assertSee('Taxa Comida · +40.000')
            ->call('sentarNoLugar', $ana->id)
            ->assertSet('aviso', 'Ana sentou na Mesa 01 · Lugar 5: entregar 60.000 fichas (40.000 de Taxa Comida).');

        Livewire::test(Sorteio::class, ['jogo' => $this->jogo])
            ->dispatch('abrir-sorteio')
            ->call('verAba', 'sentados')
            ->assertSee('Taxa Comida · +40.000');
    }

    public function test_painel_do_jogador_marca(): void
    {
        $ana = $this->jogador('Ana');
        $this->entrar($ana, TipoItem::Buyin);
        $this->actingAs($this->admin, 'web');

        Livewire::test(AcoesJogador::class, ['jogo' => $this->jogo, 'participanteId' => $ana->id])
            ->assertSee(['Taxa Comida · não pagou antes', 'Quem pagou antes leva junto do buy-in · 40.000 fichas', 'Marcar'])
            ->call('alternarOpcionalPago', $this->pizza->id)
            ->assertSet('aviso', 'Ana pagou Taxa Comida antes: entregar 40.000 fichas.')
            ->assertDispatched('caixa-mudou')
            ->assertSee(['Taxa Comida · pago antes', 'Vai junto do buy-in · 40.000 fichas', 'Desmarcar', '60.000']);

        // O Operador vê, mas não marca.
        $this->actingAs($this->operador, 'web');
        Livewire::test(AcoesJogador::class, ['jogo' => $this->jogo, 'participanteId' => $ana->id])
            ->assertSee('Taxa Comida · pago antes')
            ->assertDontSee('Desmarcar');
    }
}
