<?php

namespace Tests\Feature\Painel;

use App\Filament\Resources\Mesas\MesaResource;
use App\Filament\Resources\Mesas\Pages\ManageMesas;
use App\Models\Jogo;
use App\Models\Mesa;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

class MesasTest extends TestCase
{
    use RefreshDatabase, UsaPainel;

    public function test_admin_ve_a_lista(): void
    {
        $this->entrarNoPainel('Admin');
        $mesas = Mesa::factory()->count(2)->create();

        $this->get(MesaResource::getUrl('index'))->assertOk()->assertSee('Mesas');

        Livewire::test(ManageMesas::class)->assertCanSeeTableRecords($mesas);
    }

    public function test_sem_permissao_nao_entra(): void
    {
        $this->entrarNoPainel('Geral'); // Geral não tem mesas.ver

        $this->get(MesaResource::getUrl('index'))->assertForbidden();
    }

    public function test_cria_mesa(): void
    {
        $this->entrarNoPainel('Admin');

        Livewire::test(ManageMesas::class)
            ->callAction('create', data: [
                'nome' => 'Mesa 03',
                'lugares' => 8,
                'valor_aluguel' => '150.50',
                'ativa' => true,
            ])
            ->assertHasNoFormErrors();

        $mesa = Mesa::query()->sole();
        $this->assertSame('Mesa 03', $mesa->nome);
        $this->assertSame(8, $mesa->lugares);
        $this->assertSame('150.50', $mesa->valor_aluguel);
    }

    public function test_valida_nome_unico_e_lugares(): void
    {
        $this->entrarNoPainel('Admin');
        Mesa::factory()->create(['nome' => 'Mesa 01']);

        Livewire::test(ManageMesas::class)
            ->callAction('create', data: ['nome' => 'Mesa 01', 'lugares' => 20])
            ->assertHasFormErrors(['nome' => 'unique', 'lugares' => 'max']);
    }

    public function test_mesa_usada_em_jogo_nao_se_exclui(): void
    {
        $this->entrarNoPainel('Admin');
        $usada = Mesa::factory()->create();
        $livre = Mesa::factory()->create();
        Jogo::factory()->create()->mesas()->create(['mesa_id' => $usada->id, 'lugares' => 9]);

        Livewire::test(ManageMesas::class)
            ->assertActionHidden(TestAction::make('delete')->table($usada))
            ->assertActionVisible(TestAction::make('delete')->table($livre))
            ->callAction(TestAction::make('delete')->table($livre));

        $this->assertModelMissing($livre);
        $this->assertModelExists($usada);
    }

    public function test_quem_so_ve_nao_cria_edita_nem_exclui(): void
    {
        $conta = $this->entrarNoPainel('Admin');
        $conta->syncRoles([]);
        $conta->givePermissionTo('mesas.ver');
        $mesa = Mesa::factory()->create();

        Livewire::test(ManageMesas::class)
            ->assertActionHidden('create')
            ->assertActionHidden(TestAction::make('edit')->table($mesa))
            ->assertActionHidden(TestAction::make('delete')->table($mesa));
    }
}
