<?php

namespace Tests\Feature\Painel;

use App\Filament\Resources\EstruturasBlinds\EstruturaBlindsResource;
use App\Filament\Resources\EstruturasBlinds\Pages\CreateEstruturaBlinds;
use App\Filament\Resources\EstruturasBlinds\Pages\EditEstruturaBlinds;
use App\Filament\Resources\EstruturasBlinds\Pages\ListEstruturasBlinds;
use App\Models\EstruturaBlinds;
use App\Models\Jogo;
use Database\Seeders\EstruturasBlindsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

class EstruturasBlindsTest extends TestCase
{
    use RefreshDatabase, UsaPainel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entrarNoPainel('Admin');
        Repeater::fake();
    }

    private function nivel(int $small, int $big, int $ante = 0, int $minutos = 20): array
    {
        return ['e_intervalo' => false, 'small_blind' => $small, 'big_blind' => $big, 'ante' => $ante, 'duracao_minutos' => $minutos, 'nome_intervalo' => null];
    }

    public function test_cria_estrutura_com_niveis_e_intervalo(): void
    {
        Livewire::test(CreateEstruturaBlinds::class)
            ->fillForm([
                'nome' => 'Sábado 20 min',
                'ativo' => true,
                'niveis' => [
                    $this->nivel(25, 50),
                    $this->nivel(50, 100),
                    ['e_intervalo' => true, 'duracao_minutos' => 10, 'nome_intervalo' => ''],
                    $this->nivel(100, 200, 25),
                ],
            ])
            ->assertSee(['Como fica', '25 / 50', '100 / 200', '0:50', '1h10']) // prévia antes de salvar
            ->call('create')
            ->assertHasNoFormErrors();

        $estrutura = EstruturaBlinds::query()->sole();

        // O intervalo não conta no número: o nível 3 começa em 0:50.
        $this->assertSame(
            [[1, '25 / 50', null, '0:00'], [2, '50 / 100', null, '0:20'], [null, 'Intervalo', null, '0:40'], [3, '100 / 200', '25', '0:50']],
            array_map(fn (array $l) => [$l['numero'], $l['blinds'], $l['ante'], $l['comeca']], EstruturaBlinds::linhas($estrutura->niveis)),
        );
        $this->assertSame(
            [[1, 25, 50, 0, false, null], [2, 50, 100, 0, false, null], [3, null, null, 0, true, 'Intervalo'], [4, 100, 200, 25, false, null]],
            $estrutura->niveis->map(fn ($n) => [$n->ordem, $n->small_blind, $n->big_blind, $n->ante, $n->e_intervalo, $n->nome_intervalo])->all(),
        );
        $this->assertSame('100 / 200 · ante 25', $estrutura->niveis[3]->blindsComAnte());

        Livewire::test(ListEstruturasBlinds::class)
            ->assertCanSeeTableRecords([$estrutura])
            ->assertSee('3 níveis de 20 min · 1 intervalo · 1h10');
    }

    public function test_nivel_precisa_de_blinds_validos(): void
    {
        Livewire::test(CreateEstruturaBlinds::class)
            ->fillForm(['nome' => 'Errada', 'niveis' => [$this->nivel(200, 100), ['e_intervalo' => false, 'small_blind' => null, 'big_blind' => null, 'duracao_minutos' => 15]]])
            ->call('create')
            ->assertHasFormErrors(['niveis.0.big_blind' => 'gte', 'niveis.1.small_blind' => 'required', 'niveis.1.big_blind' => 'required']);

        Livewire::test(CreateEstruturaBlinds::class)
            ->fillForm(['nome' => 'Só intervalo', 'niveis' => [['e_intervalo' => true, 'duracao_minutos' => 15, 'nome_intervalo' => 'Jantar']]])
            ->call('create')
            ->assertHasFormErrors(['niveis'])
            ->assertSee('Cadastre ao menos um nível com blinds.');

        $this->assertSame(0, EstruturaBlinds::query()->count());
    }

    public function test_duplicar_copia_os_niveis(): void
    {
        $this->seed(EstruturasBlindsSeeder::class);
        $turbo = EstruturaBlinds::query()->where('nome', EstruturasBlindsSeeder::TURBO)->sole();

        Livewire::test(ListEstruturasBlinds::class)
            ->assertSee('16 níveis de 15 min · 1 intervalo · 4h15')
            ->callAction(TestAction::make('duplicar')->table($turbo));

        $copia = EstruturaBlinds::query()->where('nome', 'Turbo 15 min (cópia)')->sole();
        $this->assertSame(17, $copia->niveis()->count());
        $this->assertSame(
            $turbo->niveis->map->only(['ordem', 'small_blind', 'big_blind', 'e_intervalo'])->all(),
            $copia->niveis->map->only(['ordem', 'small_blind', 'big_blind', 'e_intervalo'])->all(),
        );
        $this->assertSame(17, $turbo->niveis()->count());

        // Da tela de edição também; o nome não repete e abre a cópia.
        Livewire::test(EditEstruturaBlinds::class, ['record' => $turbo->getRouteKey()])
            ->callAction('duplicar')
            ->assertRedirect(EstruturaBlindsResource::getUrl('edit', ['record' => EstruturaBlinds::query()->where('nome', 'Turbo 15 min (cópia 2)')->sole()]));
    }

    public function test_menu_chama_blinds(): void
    {
        $this->get(EstruturaBlindsResource::getUrl())->assertOk()->assertSee(['Blinds', 'Criar estrutura de blinds']);
    }

    public function test_gerar_proximos_niveis_no_padrao(): void
    {
        Livewire::test(CreateEstruturaBlinds::class)
            ->fillForm([
                'nome' => 'Sábado 20 min',
                'niveis' => [$this->nivel(25, 50), $this->nivel(50, 100), $this->nivel(100, 200)],
            ])
            // Sugere o padrão dos últimos níveis: dobrando, 20 min.
            ->mountAction(TestAction::make('gerarNiveis')->schemaComponent('secao-niveis'))
            ->assertSchemaStateSet(['quantos' => 5, 'tipo' => 'dobrar', 'minutos' => 20, 'intervalo_a_cada' => 0], 'mountedActionSchema0')
            ->fillForm(['quantos' => 4, 'intervalo_a_cada' => 2, 'minutos_intervalo' => 10], 'mountedActionSchema0')
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->call('create')
            ->assertHasNoFormErrors();

        $niveis = EstruturaBlinds::query()->sole()->niveis;
        $this->assertSame(
            [[25, 50, 20], [50, 100, 20], [100, 200, 20], [200, 400, 20], [400, 800, 20], ['Intervalo', 10], [800, 1600, 20], [1600, 3200, 20]],
            $niveis->map(fn ($n) => $n->e_intervalo ? [$n->nome_intervalo, $n->duracao_minutos] : [$n->small_blind, $n->big_blind, $n->duracao_minutos])->all(),
        );
        $this->assertSame(range(1, 8), $niveis->pluck('ordem')->all());
    }

    public function test_gerar_com_padrao_escolhido(): void
    {
        Livewire::test(CreateEstruturaBlinds::class)
            ->fillForm(['nome' => 'Lento', 'niveis' => [$this->nivel(100, 200, 200, 30)]])
            ->callAction(TestAction::make('gerarNiveis')->schemaComponent('secao-niveis'), data: [
                'quantos' => 2, 'tipo' => 'multiplicar', 'valor' => '1.5', 'minutos' => 25, 'intervalo_a_cada' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            ['100 / 200 · ante 200', '150 / 300 · ante 300', '225 / 450 · ante 450'],
            EstruturaBlinds::query()->sole()->niveis->map->blindsComAnte()->all(),
        );
    }

    public function test_gerar_sem_nivel_de_blinds_fica_desligado(): void
    {
        Livewire::test(CreateEstruturaBlinds::class)
            ->fillForm(['nome' => 'Vazia', 'niveis' => [['e_intervalo' => true, 'duracao_minutos' => 10]]])
            ->assertActionDisabled(TestAction::make('gerarNiveis')->schemaComponent('secao-niveis'));
    }

    public function test_estrutura_em_uso_nao_se_exclui(): void
    {
        $this->seed(EstruturasBlindsSeeder::class);
        [$deep, $turbo] = EstruturaBlinds::query()->orderBy('nome')->get()->all();
        Jogo::factory()->create(['estrutura_blinds_id' => $turbo->id]);

        Livewire::test(ListEstruturasBlinds::class)
            ->assertActionHidden(TestAction::make('delete')->table($turbo))
            ->callAction(TestAction::make('delete')->table($deep));

        $this->assertModelMissing($deep);
        $this->assertModelExists($turbo);
    }

    public function test_grupo_geral_so_ve(): void
    {
        $this->entrarNoPainel('Geral');
        $this->seed(EstruturasBlindsSeeder::class);
        $turbo = EstruturaBlinds::query()->where('nome', EstruturasBlindsSeeder::TURBO)->sole();

        Livewire::test(ListEstruturasBlinds::class)
            ->assertCanSeeTableRecords([$turbo])
            ->assertActionHidden(TestAction::make('edit')->table($turbo));

        $this->get(EstruturaBlindsResource::getUrl('create'))->assertForbidden();
    }
}
