<?php

namespace Tests\Feature\Painel;

use App\Filament\Resources\Usuarios\Pages\CreateUsuario;
use App\Filament\Resources\Usuarios\Pages\EditUsuario;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\Grupo;
use App\Models\Usuario;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    private function grupoDoSite(string $nome): int
    {
        return Grupo::findByName($nome, 'web')->id;
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

    public function test_admin_da_acesso_ao_site_e_o_operador_entra(): void
    {
        $this->entrarNoPainel('Admin');

        Livewire::test(CreateUsuario::class)
            ->fillForm([
                'nome' => 'Paulo',
                'acessa_site' => true,
                'email' => ' Paulo@Exemplo.com ',
                'password' => 'senha-segura',
                'roles' => [$this->grupoDoSite('Operador')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $paulo = Usuario::query()->sole();
        $this->assertSame('paulo@exemplo.com', $paulo->email);
        $this->assertTrue(Hash::check('senha-segura', $paulo->password));
        $this->assertTrue($paulo->hasRole('Operador'));
        $this->assertTrue($paulo->can('entradas.registrar'));
        $this->assertFalse($paulo->can('jogo.finalizar'));

        $this->withoutVite()
            ->post('/login', ['email' => 'paulo@exemplo.com', 'password' => 'senha-segura'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($paulo, 'web');
    }

    public function test_acesso_ao_site_exige_email_senha_e_grupo(): void
    {
        $this->entrarNoPainel('Admin');
        Usuario::factory()->comAcesso()->create(['email' => 'ja@exemplo.com']);

        Livewire::test(CreateUsuario::class)
            ->fillForm(['nome' => 'Sem dados', 'acessa_site' => true])
            ->call('create')
            ->assertHasFormErrors(['email' => 'required', 'password' => 'required', 'roles' => 'required'])
            ->assertSee('O campo e-mail é obrigatório.')
            ->assertSee('O campo grupos do site é obrigatório.');

        Livewire::test(CreateUsuario::class)
            ->fillForm(['nome' => 'Repetido', 'acessa_site' => true, 'email' => 'ja@exemplo.com', 'password' => 'curta'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique', 'password' => 'min']);
    }

    public function test_editar_sem_senha_mantem_a_atual(): void
    {
        $this->entrarNoPainel('Admin');
        $operador = Usuario::factory()->comAcesso()->create(['apelido' => null]);
        $operador->assignRole('Operador');

        Livewire::test(EditUsuario::class, ['record' => $operador->getRouteKey()])
            ->assertFormFieldIsVisible('password')
            ->fillForm(['apelido' => 'Paulão', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $operador->refresh();
        $this->assertSame('Paulão', $operador->apelido);
        $this->assertTrue(Hash::check('password', $operador->password));
    }

    public function test_grupo_geral_cadastra_jogador_mas_nao_da_acesso_nem_edita(): void
    {
        $this->entrarNoPainel('Geral');
        $existente = Usuario::factory()->create();

        Livewire::test(CreateUsuario::class)
            ->assertFormFieldIsHidden('acessa_site')
            ->fillForm(['nome' => 'Felipe'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('usuarios', ['nome' => 'Felipe', 'acessa_site' => false]);

        Livewire::test(ListUsuarios::class)
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
