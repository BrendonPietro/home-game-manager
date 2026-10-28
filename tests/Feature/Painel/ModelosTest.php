<?php

namespace Tests\Feature\Painel;

use App\Enums\MetodoPremiacao;
use App\Enums\Modalidade;
use App\Filament\Resources\Jogos\JogoResource;
use App\Filament\Resources\Jogos\Pages\EditJogo;
use App\Filament\Resources\Modelos\ModeloResource;
use App\Filament\Resources\Modelos\Pages\CreateModelo;
use App\Filament\Resources\Modelos\Pages\ListModelos;
use App\Models\Jogo;
use Database\Seeders\RegrasPremiacaoSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

class ModelosTest extends TestCase
{
    use RefreshDatabase, UsaPainel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entrarNoPainel('Admin');
        $this->seed(RegrasPremiacaoSeeder::class);
        Repeater::fake();
    }

    public function test_lista_so_modelos(): void
    {
        $modelo = Jogo::factory()->modelo('Torneio de sábado')->comEntradasPadrao()->create();
        $jogo = Jogo::factory()->create();

        $this->get(ModeloResource::getUrl('index'))->assertOk();

        Livewire::test(ListModelos::class)
            ->assertCanSeeTableRecords([$modelo])
            ->assertCanNotSeeTableRecords([$jogo])
            ->assertSee('Torneio de sábado')
            ->assertSee('R$ 100');
    }

    public function test_cria_modelo_sem_data(): void
    {
        Livewire::test(CreateModelo::class)
            ->assertFormFieldHidden('data')
            ->assertFormFieldHidden('nome')
            ->fillForm([
                'modalidade' => Modalidade::Torneio->value,
                'nome_modelo' => 'Torneio de sábado',
                'horario' => '19:30',
                'lugares_por_mesa' => 9,
                'tamanho_mesa_final' => 9,
                'itens_buyin' => [['padrao' => true, 'nome' => 'Buy-in', 'valor' => '100', 'fichas' => 20000, 'limite_por_jogador' => 1, 'ativo' => true]],
                'itens_rebuy' => [['padrao' => true, 'nome' => 'Rebuy', 'valor' => '100', 'fichas' => 20000, 'limite_por_jogador' => null, 'ativo' => true]],
                'itens_addon' => [['padrao' => true, 'nome' => 'Add-on', 'valor' => '50', 'fichas' => 20000, 'limite_por_jogador' => 1, 'ativo' => true]],
                'metodo_premiacao' => MetodoPremiacao::Regra->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $modelo = Jogo::query()->modelos()->sole();
        $this->assertSame(['Torneio de sábado', 'Torneio de sábado', null], [$modelo->nome_modelo, $modelo->nome, $modelo->data]);
        $this->assertSame(3, $modelo->itens()->count());
    }

    public function test_criar_jogo_pela_lista(): void
    {
        $modelo = Jogo::factory()->modelo('Torneio de sábado')->comEntradasPadrao()->create();

        Livewire::test(ListModelos::class)
            ->callAction(TestAction::make('criarJogo')->table($modelo), data: ['nome' => 'Poker Night #20', 'data' => '2026-11-07', 'horario' => '20:00'])
            ->assertHasNoFormErrors();

        $jogo = Jogo::query()->reais()->sole();
        $this->assertSame('Poker Night #20', $jogo->nome);
        $this->assertSame(3, $jogo->itens()->count());
    }

    public function test_duplicar_e_salvar_como_modelo_pelo_jogo(): void
    {
        $jogo = Jogo::factory()->agendado()->comEntradasPadrao()->create(['nome' => 'Poker Night #19']);

        Livewire::test(EditJogo::class, ['record' => $jogo->getRouteKey()])
            ->callAction('duplicar', data: ['nome' => 'Poker Night #20'])
            ->assertRedirect(JogoResource::getUrl('edit', ['record' => Jogo::query()->where('nome', 'Poker Night #20')->sole()]));

        Livewire::test(EditJogo::class, ['record' => $jogo->getRouteKey()])
            ->callAction('salvarComoModelo', data: ['nome_modelo' => 'Domingo']);

        $this->assertSame(['Domingo'], Jogo::query()->modelos()->pluck('nome_modelo')->all());
        $this->assertSame(3, Jogo::query()->modelos()->sole()->itens()->count());
    }

    public function test_geral_nao_ve_modelos(): void
    {
        $this->entrarNoPainel('Geral');

        $this->get(ModeloResource::getUrl('index'))->assertForbidden();
    }
}
