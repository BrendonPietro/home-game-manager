<?php

namespace Tests\Feature\Site;

use App\Actions\Jogo\RegistrarCashOut;
use App\Enums\TipoItem;
use App\Livewire\Jogo\AcoesJogador;
use App\Livewire\Jogo\Resumo;
use App\Models\Jogo;
use App\Models\Participante;
use Database\Seeders\PokerNight18Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class ResumoTest extends TestCase
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
        $this->actingAs($this->operador, 'web');
    }

    private function da18(string $nome): Participante
    {
        return $this->noite->participantes()->whereHas('usuario', fn ($q) => $q->where('nome', $nome))->sole();
    }

    public function test_numeros_e_tabela_da_noite_18(): void
    {
        $this->get(route('jogos.resumo', $this->noite))
            ->assertOk()
            ->assertSee('wire:poll.5s', false)
            ->assertSeeInOrder(['Resumo ao vivo', 'Jogando', '12', 'Buy-ins', '12', 'Rebuys', '6', 'Add-ons', '4', 'Total de entradas', '22'])
            ->assertSeeInOrder(['Arrecadação', 'R$ 2.100', 'Casa 10%', 'R$ 210', 'Prize pool', 'R$ 1.890'])
            ->assertSeeInOrder(['Jogadores devem ao caixa', 'R$ 2.100', 'Caixa deve aos jogadores', 'R$ 0', 'Entrou no caixa', 'R$ 0'])
            // Maior total primeiro; no empate, pelo nome.
            ->assertSeeInOrder(['Brendon', 'Carlos', 'Marcão', 'André', 'Lucas', 'Pedro', 'Rafael', 'João', 'Bruno', 'Diego', 'Felipe', 'Thiago'])
            ->assertSeeInOrder(['Rafael', 'Mesa 01 · 5', 'Buy-in duplo', '20.000', 'R$ 200', 'deve R$ 200'])
            ->assertSeeInOrder(['12 jogadores', '6', '4', '230.000', 'R$ 2.100']);
    }

    public function test_filtra_por_mesa_e_por_pendencia(): void
    {
        $felipe = $this->da18('Felipe');
        $this->pagar($felipe, 10000);

        Livewire::test(Resumo::class, ['jogo' => $this->noite])
            ->assertSeeInOrder(['Felipe', 'quitado'])
            ->assertSee('Só pendentes (11)')
            ->set('soPendentes', true)
            ->assertDontSee('Felipe')
            ->assertSee('11 de 12 jogadores')
            ->set('soPendentes', false)
            ->call('filtrarMesa', $this->noite->mesas()->where('ordem', 2)->value('id'))
            ->assertSee('Brendon')
            ->assertDontSee('Carlos')
            ->call('filtrarMesa', null)
            ->assertSee('Carlos');
    }

    public function test_clicar_no_jogador_abre_o_painel_sem_mudar_de_lugar(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::test(Resumo::class, ['jogo' => $this->noite])
            ->call('selecionar', $this->da18('João')->id)
            ->assertSeeLivewire(AcoesJogador::class)
            ->assertSee('REGISTRAR PAGAMENTO')
            ->assertDontSee('MUDAR DE LUGAR')
            ->dispatch('fechar-jogador')
            ->assertSet('selecionado', null);
    }

    public function test_cash_game_mostra_recompras_e_cash_out(): void
    {
        $ana = $this->jogador('Ana');
        $this->entrar($ana, TipoItem::Buyin);
        $this->entrar($ana, TipoItem::Rebuy);
        app(RegistrarCashOut::class)($ana, 30000, $this->operador);

        $this->get(route('jogos.resumo', $this->jogo))
            ->assertOk()
            ->assertSeeInOrder(['Recompras', 'Cash-out', 'Pago', 'Saldo'])
            ->assertDontSee('Add-ons')
            ->assertSeeInOrder(['Ana', 'saiu', 'R$ 200', 'R$ 300', 'recebe R$ 100']);
    }

    public function test_jogo_agendado_vai_para_a_conferencia(): void
    {
        $agendado = Jogo::factory()->agendado()->create();

        $this->get(route('jogos.resumo', $agendado))->assertRedirect(route('jogos.conferencia', $agendado));
    }
}
