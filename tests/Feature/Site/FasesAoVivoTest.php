<?php

namespace Tests\Feature\Site;

use App\Enums\FaseTorneio;
use App\Enums\TipoItem;
use App\Livewire\Jogo\AoVivo;
use App\Models\Jogo;
use Database\Seeders\PokerNight18Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class FasesAoVivoTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->montarNoite();
        $this->actingAs($this->admin, 'web');
    }

    public function test_fechar_entradas_da_noite_18(): void
    {
        $this->seed(PokerNight18Seeder::class);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();

        Livewire::test(AoVivo::class, ['jogo' => $noite])
            ->assertSee('FECHAR ENTRADAS')
            ->call('abrirFase')
            ->assertSet('modal', 'fechar')
            // Tela 11: o que deixa de valer, a conta e a premiação.
            ->assertSee('novos add-ons não poderão ser registrados')
            ->assertSeeInOrder(['Total arrecadado', 'R$ 2.100', 'Casa (10%)', 'R$ 210', 'Prize pool', 'R$ 1.890'])
            ->assertSee('−R$ 210')
            ->assertSee('1º R$ 950 · 2º R$ 565 · 3º R$ 375')
            ->assertSee('22 entradas · 12 buy-ins, 6 rebuys, 4 add-ons')
            ->call('fecharEntradas')
            ->assertSet('modal', null)
            ->assertSee('Entradas fechadas e prêmios gravados')
            ->assertDontSee('FECHAR ENTRADAS')
            ->assertSee(['Premiação', '1º', '2º', '3º', 'em jogo', 'R$ 950', 'R$ 565', 'R$ 375']);

        $this->assertSame(FaseTorneio::SemEntradas, $noite->fresh()->fase);
    }

    public function test_encerrar_rebuys_avisa_quem_fica_fora_e_o_addon(): void
    {
        $joao = $this->jogador('João');
        $lucas = $this->jogador('Lucas');
        $this->jogador('Zeca'); // sentado sem buy-in
        $this->entrar($joao, TipoItem::Buyin);
        $this->entrar($lucas, TipoItem::Buyin);
        $this->eliminar($joao);

        $tela = Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->assertSee('ENCERRAR REBUYS')
            ->call('abrirFase')
            ->assertSet('modal', 'encerrar')
            ->assertSee('o add-on abre (R$ 100, 1 por jogador);')
            ->assertSee('João fica eliminado de vez.')
            ->call('encerrarRebuys')
            ->assertSee('Rebuys encerrados: o add-on está aberto.')
            ->assertSeeInOrder(['Fase', 'Intervalo (add-on)'])
            ->assertSee('FECHAR ENTRADAS');

        $this->assertSame(FaseTorneio::Intervalo, $this->jogo->fresh()->fase);

        // Fechar entradas avisa quem está jogando sem buy-in.
        $tela->call('abrirFase')
            ->assertSet('modal', 'fechar')
            ->assertSee('Sem buy-in:')
            ->assertSee('Zeca. Lance antes de fechar ou fica fora da premiação.');
    }

    public function test_nivel_e_blinds_pela_faixa(): void
    {
        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->call('abrirNivel')
            ->assertSet('modal', 'nivel')
            ->set('nivel', 'x')
            ->call('salvarNivel')
            ->assertSee('O nível é um número.')
            ->set('nivel', '7')
            ->set('blinds', '200/400')
            ->call('salvarNivel')
            ->assertSet('modal', null)
            ->assertSee(['Nível 7', '200 / 400']);
    }

    public function test_com_estrutura_de_blinds_o_nivel_segue_o_relogio(): void
    {
        $this->seed(PokerNight18Seeder::class);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();

        // Relógio pausado no nível 6 com 14:32 (Turbo 15 min): o nível não se digita.
        Livewire::test(AoVivo::class, ['jogo' => $noite])
            ->assertSee(['Nível 6', '100 / 200'])
            ->assertDontSeeHtml('wire:click="abrirNivel"');
    }

    public function test_operador_nao_muda_fase_nem_nivel(): void
    {
        $this->actingAs($this->operador, 'web');

        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->assertDontSee('ENCERRAR REBUYS')
            ->assertDontSeeHtml('wire:click="abrirNivel"')
            ->call('encerrarRebuys')
            ->assertSee('Só o Admin do site muda a fase do torneio.');

        $this->assertSame(FaseTorneio::Rebuys, $this->jogo->fresh()->fase);
    }
}
