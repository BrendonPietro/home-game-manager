<?php

namespace Tests\Feature\Site;

use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\RegistrarCashOut;
use App\Actions\Jogo\RegistrarEliminacao;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Livewire\Jogo\AcoesJogador;
use App\Livewire\Jogo\Fechamento;
use App\Models\Jogo;
use App\Models\Participante;
use Database\Seeders\PokerNight18Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class FechamentoTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    private Jogo $noite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // A noite do MontaNoite é um cash game; a #18 é o torneio de referência.
        $this->montarNoite(Jogo::factory()->cashGame()->emAndamento()->comEntradasPadrao()->create());
        $this->seed(PokerNight18Seeder::class);
        $this->noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
        $this->actingAs($this->admin, 'web');
    }

    private function da18(string $nome): Participante
    {
        return $this->noite->participantes()->whereHas('usuario', fn ($q) => $q->where('nome', $nome))->sole();
    }

    /** Fecha as entradas da #18 e elimina na ordem da lista; parando antes, sobra gente sem posição. */
    private function eliminar18(int $quantos = 11): void
    {
        app(FecharEntradas::class)($this->noite, $this->admin);

        foreach (array_slice(['João', 'Lucas', 'Carlos', 'Rafael', 'André', 'Bruno', 'Pedro', 'Brendon', 'Thiago', 'Diego', 'Felipe'], 0, $quantos) as $nome) {
            app(RegistrarEliminacao::class)($this->da18($nome), $this->admin);
        }
    }

    public function test_so_abre_depois_de_fechar_as_entradas(): void
    {
        $this->get(route('jogos.fechamento', $this->noite))
            ->assertRedirect(route('jogos.ao-vivo', $this->noite))
            ->assertSessionHas('aviso', 'Fechamento abre depois de fechar as entradas.');
    }

    public function test_falta_posicao_nao_finaliza(): void
    {
        $this->eliminar18(9); // sobram Marcão, Diego e Felipe

        Livewire::test(Fechamento::class, ['jogo' => $this->noite])
            ->assertSee('Falta a posição de Marcão, Diego e Felipe.')
            // Nenhum premiado definido: o prize pool inteiro ainda está sem dono.
            ->assertSeeInOrder(['Conferência', 'R$ 1.890', 'do prize pool ainda sem dono'])
            ->call('abrirFinalizar')
            ->call('finalizar')
            ->assertSee('Falta a posição de Marcão, Diego e Felipe.')
            ->assertSet('confirmando', true);

        $this->assertSame(StatusJogo::EmAndamento, $this->noite->fresh()->status);
    }

    public function test_finalizar_a_noite_18_e_marcar_os_acertos(): void
    {
        $this->eliminar18();

        Livewire::test(Fechamento::class, ['jogo' => $this->noite])
            ->assertSeeInOrder(['Total investido', 'R$ 2.100', 'Prêmios', 'R$ 1.890', 'Fica com a casa', 'R$ 210'])
            ->assertSee('R$ 0 de diferença')
            // Maior resultado primeiro: Marcão (+R$ 695) e Felipe (+R$ 467).
            ->assertSeeInOrder(['Por jogador', 'Marcão', '+R$ 695', 'Felipe', '+R$ 467'])
            ->assertSee('(sugestão)')
            ->assertSee('Caixa')
            ->assertSee('FINALIZAR TORNEIO')
            ->call('abrirFinalizar')
            ->assertSee('12 jogadores ficam com saldo em aberto (pendência).')
            ->call('finalizar')
            ->assertRedirect(route('jogos.fechamento', $this->noite));

        $this->assertSame(StatusJogo::Finalizado, $this->noite->fresh()->status);
        $this->assertSame('Torneio finalizado: os acertos abaixo estão gravados.', session('aviso'));

        // Recarregada, a tela mostra os acertos gravados para marcar como feitos.
        $tela = Livewire::test(Fechamento::class, ['jogo' => $this->noite->fresh()])
            ->assertDontSee('(sugestão)')
            ->assertDontSee('FINALIZAR TORNEIO')
            ->assertSee('FEITO');

        $primeiro = $this->noite->acertos()->orderBy('id')->first();
        $tela->set('forma', 'dinheiro')
            ->call('marcarAcerto', $primeiro->id)
            ->assertSee('Acerto marcado como feito.');

        $this->assertTrue($primeiro->fresh()->pago);
        $this->assertSame('dinheiro', $this->noite->pagamentos()->latest('id')->value('forma')->value);
    }

    public function test_depois_de_finalizar_o_painel_so_paga(): void
    {
        $this->eliminar18();
        Livewire::test(Fechamento::class, ['jogo' => $this->noite])->call('finalizar');

        Livewire::test(AcoesJogador::class, ['jogo' => $this->noite->fresh(), 'participanteId' => $this->da18('Marcos')->id, 'mudaLugar' => false])
            ->assertSee('REGISTRAR PAGAMENTO')
            ->assertDontSee('Desfazer')
            ->assertDontSee('REGISTRAR ELIMINAÇÃO');
    }

    public function test_cash_game_confere_cash_outs(): void
    {
        $ana = $this->jogador('Ana');
        $beto = $this->jogador('Beto');
        $this->entrar($ana, TipoItem::Buyin);
        $this->entrar($beto, TipoItem::Buyin);

        // Ficha de R$ 0,01: saem R$ 150 + R$ 40 de R$ 200 em jogo.
        app(RegistrarCashOut::class)($ana, 15000, $this->operador);
        app(RegistrarCashOut::class)($beto, 4000, $this->operador);

        Livewire::test(Fechamento::class, ['jogo' => $this->jogo])
            ->assertSeeInOrder(['Cash-outs', 'R$ 190', 'Conferência', 'R$ 10', 'a mais na premiação do que nos cash-outs'])
            ->assertSee('FINALIZAR JOGO')
            ->assertDontSee('Falta o cash-out');
    }
}
