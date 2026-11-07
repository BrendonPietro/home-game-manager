<?php

namespace Tests\Feature\Painel;

use App\Filament\Resources\Grupos\GrupoResource;
use App\Filament\Resources\Grupos\Pages\CreateGrupo;
use App\Filament\Resources\Grupos\Pages\EditGrupo;
use App\Filament\Resources\Grupos\Pages\ListGrupos;
use App\Filament\Resources\Segurancas\Pages\ManageSegurancas;
use App\Filament\Resources\Segurancas\SegurancaResource;
use App\Models\Grupo;
use App\Models\Seguranca;
use App\Support\Permissoes;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

/** Contas do painel (Segurança) e grupos com a grade de permissões. */
class SegurancaTest extends TestCase
{
    use RefreshDatabase, UsaPainel;

    private function grupo(string $nome, string $guard = 'painel'): Grupo
    {
        return Grupo::findByName($nome, $guard);
    }

    public function test_admin_cria_conta_com_grupo(): void
    {
        $this->entrarNoPainel('Admin');
        $this->get(SegurancaResource::getUrl())->assertOk();

        Livewire::test(ManageSegurancas::class)
            ->callAction('create', data: [
                'nome' => 'Ana do caixa', 'login' => ' Ana_Caixa ', 'password' => 'segredo-longo', 'ativo' => true,
                'roles' => [$this->grupo('Geral')->id],
            ])
            ->assertHasNoFormErrors();

        $conta = Seguranca::query()->where('login', 'ana_caixa')->sole();
        $this->assertTrue(Hash::check('segredo-longo', $conta->password));
        $this->assertTrue($conta->can('usuarios.criar'));
        $this->assertFalse($conta->can('financeiro.ver'));
    }

    public function test_ninguem_da_o_que_nao_tem(): void
    {
        $admin = $this->entrarNoPainel('Admin');
        $dev = Seguranca::factory()->create(['nome' => 'Dev']);
        $dev->assignRole('Desenvolvedor');
        $geral = Seguranca::factory()->create(['nome' => 'Geral', 'login' => 'joao.geral']); // ponto no login vale
        $geral->assignRole('Geral');

        // Admin não tem grupos.*: não dá o grupo Desenvolvedor nem a permissão avulsa.
        Livewire::test(ManageSegurancas::class)
            ->callAction('create', data: ['nome' => 'X', 'login' => 'x', 'password' => 'segredo-longo', 'roles' => [$this->grupo('Desenvolvedor')->id]])
            ->assertHasFormErrors(['roles']);

        // Nem mexe na conta do Desenvolvedor; a do Geral, sim. A própria não se exclui.
        Livewire::test(ManageSegurancas::class)
            ->assertActionHidden(TestAction::make('edit')->table($dev))
            ->assertActionHidden(TestAction::make('delete')->table($dev))
            ->assertActionVisible(TestAction::make('edit')->table($geral))
            ->assertActionHidden(TestAction::make('delete')->table($admin))
            ->callAction(TestAction::make('edit')->table($geral), data: ['nome' => 'Geral 2', 'password' => ''])
            ->assertHasNoFormErrors();

        // Senha em branco mantém a atual.
        $this->assertSame('Geral 2', $geral->fresh()->nome);
        $this->assertTrue(Hash::check('password', $geral->fresh()->password));
    }

    public function test_geral_nao_ve_seguranca_nem_grupos(): void
    {
        $this->entrarNoPainel('Geral');

        $this->get(SegurancaResource::getUrl())->assertForbidden();
        $this->get(GrupoResource::getUrl())->assertForbidden();
    }

    public function test_grade_de_permissoes_na_ordem_das_telas(): void
    {
        $this->entrarNoPainel('Desenvolvedor');

        $painel = Permissoes::opcoes('painel');
        $this->assertSame(['jogos.ver', 'jogos.criar', 'jogos.editar', 'jogos.excluir', 'modelos.ver'], array_slice(array_keys($painel), 0, 5));
        $this->assertSame(['relatorios.ver', 'relatorios.exportar', 'financeiro.ver', 'configuracoes.editar', 'pagamentos.registrar'], array_slice(array_keys($painel), -5));
        $this->assertSame(['Jogos · ver', 'Agenda · editar'], [$painel['jogos.ver'], $painel['eventos.editar']]);
        $this->assertSame('Lançar entradas', Permissoes::opcoes('web')['entradas.registrar']);
    }

    public function test_desenvolvedor_cria_e_edita_grupo(): void
    {
        $this->entrarNoPainel('Desenvolvedor');

        Livewire::test(CreateGrupo::class)
            ->fillForm(['name' => 'Caixa', 'guard_name' => 'painel', 'permissoes' => ['jogos.ver', 'financeiro.ver']])
            ->call('create')
            ->assertHasNoFormErrors();

        $caixa = $this->grupo('Caixa');
        $conta = Seguranca::factory()->create();
        $conta->assignRole($caixa);
        $this->assertTrue($conta->can('financeiro.ver'));

        Livewire::test(EditGrupo::class, ['record' => $caixa->getRouteKey()])
            ->assertSchemaStateSet(['permissoes' => ['jogos.ver', 'financeiro.ver']])
            ->fillForm(['name' => 'Caixa da noite', 'permissoes' => ['jogos.ver', 'relatorios.ver']])
            ->call('save')
            ->assertHasNoFormErrors();

        // syncPermissions limpa o cache: vale na hora.
        $conta = $conta->fresh();
        $this->assertSame('Caixa da noite', $caixa->fresh()->name);
        $this->assertFalse($conta->can('financeiro.ver'));
        $this->assertTrue($conta->can('relatorios.ver'));

        // Grupo do site com as permissões do site.
        Livewire::test(CreateGrupo::class)
            ->fillForm(['name' => 'Caixa', 'guard_name' => 'web', 'permissoes' => ['pagamentos.registrar']])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(['pagamentos.registrar'], $this->grupo('Caixa', 'web')->permissions->pluck('name')->all());
    }

    public function test_grupo_com_gente_nao_se_exclui(): void
    {
        $this->entrarNoPainel('Desenvolvedor');
        $vazio = Grupo::create(['name' => 'Vazio', 'guard_name' => 'painel']);

        Livewire::test(ListGrupos::class)
            ->assertActionHidden(TestAction::make('delete')->table($this->grupo('Desenvolvedor')))
            ->callAction(TestAction::make('delete')->table($vazio));

        $this->assertModelMissing($vazio);
    }
}
