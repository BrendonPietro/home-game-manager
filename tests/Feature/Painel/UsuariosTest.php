<?php

namespace Tests\Feature\Painel;

use App\Filament\Resources\Usuarios\Pages\CreateUsuario;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\Usuario;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use MontaNoite, RefreshDatabase, UsaPainel;

    public function test_saldo_em_aberto_dos_jogos_finalizados(): void
    {
        $this->entrarNoPainel('Admin');
        // Ana: o caixa deve 195; Beto: deve 5; Caio deve 100 ao caixa.
        [$ana, $beto, $caio] = array_map(fn ($p) => $p->usuario, $this->noiteFinalizadaDeTres());
        $semJogo = Usuario::factory()->create(['nome' => 'Zé', 'apelido' => null]);

        $this->assertSame(
            ['Ana' => '-195.00', 'Beto' => '-5.00', 'Caio' => '100.00', 'Zé' => '0.00'],
            Usuario::query()->comSaldoEmAberto()->whereKey([$ana->id, $beto->id, $caio->id, $semJogo->id])->orderBy('nome')
                ->get()->mapWithKeys(fn (Usuario $u) => [$u->nome => number_format((float) $u->saldo_em_aberto, 2, '.', '')])->all(),
        );

        Livewire::test(ListUsuarios::class)
            ->assertSee(['+R$ 100', '−R$ 195', '−R$ 5'])
            ->sortTable('saldo_em_aberto', 'desc')
            ->assertCanSeeTableRecords([$caio, $semJogo, $beto, $ana], inOrder: true);

        // Sem financeiro.ver, a coluna some.
        $this->entrarNoPainel('Geral');
        Livewire::test(ListUsuarios::class)->assertTableColumnHidden('saldo_em_aberto');
    }

    public function test_lista_e_busca_por_apelido(): void
    {
        $this->entrarNoPainel('Admin');
        $marcao = Usuario::factory()->create(['nome' => 'Marcos Silva', 'apelido' => 'Marcão']);
        $thiago = Usuario::factory()->create(['nome' => 'Thiago Souza', 'apelido' => null]);

        $this->get(UsuarioResource::getUrl('index'))->assertOk();

        Livewire::test(ListUsuarios::class)
            ->assertCanSeeTableRecords([$marcao, $thiago])
            ->searchTable('Marcão')
            ->assertCanSeeTableRecords([$marcao])
            ->assertCanNotSeeTableRecords([$thiago]);
    }

    public function test_colunas_dealer_floor_e_ativo_num_clique(): void
    {
        $this->entrarNoPainel('Admin');
        $sergio = Usuario::factory()->dealer()->create(['nome' => 'Sérgio', 'apelido' => null]);
        $ana = Usuario::factory()->create(['nome' => 'Ana', 'apelido' => null, 'pode_ser_floor' => true]);

        Livewire::test(ListUsuarios::class)
            ->assertTableColumnStateSet('dealer', 'Sim', $sergio)
            ->assertTableColumnStateNotSet('dealer', 'Sim', $ana)
            ->assertTableColumnStateSet('floor', 'Sim', $ana)
            ->assertDontSee(['Acesso ao site', 'Na noite'])
            ->assertSee('Ações')
            // O check vira o X vermelho e volta, sem modal.
            ->callAction(TestAction::make('ativo')->table($ana))
            ->assertHasNoActionErrors();

        $this->assertFalse($ana->fresh()->ativo);

        Livewire::test(ListUsuarios::class)
            ->assertActionHasLabel(TestAction::make('ativo')->table($ana), 'Inativo')
            ->callAction(TestAction::make('ativo')->table($ana));

        $this->assertTrue($ana->fresh()->ativo);
    }

    public function test_cadastro_minimo_so_com_nome(): void
    {
        $this->entrarNoPainel('Admin');

        Livewire::test(CreateUsuario::class)
            ->fillForm(['nome' => 'Diego'])
            ->call('create')
            ->assertHasNoFormErrors();

        $diego = Usuario::query()->sole();
        $this->assertSame('Diego', $diego->nome);
        $this->assertTrue($diego->ativo);
        $this->assertFalse($diego->acessa_site);
    }

    public function test_grupo_geral_cadastra_jogador_mas_nao_da_acesso_nem_edita(): void
    {
        $this->entrarNoPainel('Geral');
        $existente = Usuario::factory()->create();

        // Só jogador: nada de acesso ao site neste cadastro.
        Livewire::test(CreateUsuario::class)
            ->assertDontSee(['Usuários do site', 'Na noite'])
            ->assertSee(['Pode ser dealer', 'Pode ser floor'])
            ->fillForm(['nome' => 'Felipe'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('usuarios', ['nome' => 'Felipe', 'acessa_site' => false]);

        Livewire::test(ListUsuarios::class)
            ->assertActionHidden(TestAction::make('ativo')->table($existente))
            ->assertActionHidden(TestAction::make('edit')->table($existente))
            ->assertActionHidden(TestAction::make('delete')->table($existente));

        $this->get(UsuarioResource::getUrl('edit', ['record' => $existente]))->assertForbidden();
    }

    public function test_excluir_e_soft_delete_e_da_para_restaurar(): void
    {
        $this->entrarNoPainel('Admin');
        $usuario = Usuario::factory()->create();

        Livewire::test(ListUsuarios::class)
            ->callAction(TestAction::make('delete')->table($usuario));

        $this->assertSoftDeleted($usuario);

        Livewire::test(ListUsuarios::class)
            ->filterTable('trashed', true)
            ->callAction(TestAction::make('restore')->table($usuario));

        $this->assertNotSoftDeleted($usuario);
    }
}
