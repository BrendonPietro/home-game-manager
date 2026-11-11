<?php

namespace Tests\Feature\Painel;

use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Filament\Resources\UsuariosDoSite\Pages\ManageUsuariosDoSite;
use App\Filament\Resources\UsuariosDoSite\UsuarioDoSiteResource;
use App\Models\Grupo;
use App\Models\Usuario;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

/** Sistema → Usuários do site: quem entra no site (e-mail, senha e grupos), com a permissão `acessos.*`. */
class UsuariosDoSiteTest extends TestCase
{
    use RefreshDatabase;
    use UsaPainel;

    private function grupoDoSite(string $nome): int
    {
        return Grupo::findByName($nome, 'web')->id;
    }

    public function test_novo_usuario_do_site_entra(): void
    {
        $this->entrarNoPainel('Admin');

        Livewire::test(ManageUsuariosDoSite::class)
            ->callAction('create', data: [
                'nome' => 'Paulo',
                'email' => ' Paulo@Exemplo.com ',
                'password' => 'senha-segura',
                'roles' => [$this->grupoDoSite('Operador')],
            ])
            ->assertHasNoActionErrors();

        $paulo = Usuario::query()->sole();
        $this->assertSame(['paulo@exemplo.com', true], [$paulo->email, $paulo->acessa_site]);
        $this->assertTrue(Hash::check('senha-segura', $paulo->password));
        $this->assertTrue($paulo->hasRole('Operador'));

        // Aparece também em Jogadores (é a mesma pessoa).
        Livewire::test(ListUsuarios::class)->assertCanSeeTableRecords([$paulo]);

        $this->withoutVite()
            ->post('/login', ['login' => 'paulo@exemplo.com', 'password' => 'senha-segura'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($paulo, 'web');
    }

    public function test_dar_acesso_a_um_jogador_e_tirar_depois(): void
    {
        $this->entrarNoPainel('Admin');
        $diego = Usuario::factory()->create(['nome' => 'Diego', 'apelido' => null]);

        Livewire::test(ManageUsuariosDoSite::class)
            ->assertCanNotSeeTableRecords([$diego])
            ->callAction('darAcesso', data: [
                'usuario' => $diego->id,
                'email' => 'diego@exemplo.com',
                'password' => 'senha-segura',
                'roles' => [$this->grupoDoSite('Admin')],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Diego já pode entrar no site.');

        $diego->refresh();
        $this->assertSame([true, 'diego@exemplo.com'], [$diego->acessa_site, $diego->email]);
        $this->assertTrue($diego->hasRole('Admin'));

        Livewire::test(ManageUsuariosDoSite::class)
            ->assertCanSeeTableRecords([$diego])
            ->callAction(TestAction::make('tirarAcesso')->table($diego))
            ->assertNotified('Diego não entra mais no site.');

        $diego->refresh();
        $this->assertFalse($diego->acessa_site);
        $this->assertSame([], $diego->getRoleNames()->all());
        $this->assertNotSoftDeleted($diego); // continua em Jogadores
    }

    public function test_exige_usuario_ou_email_senha_e_grupo(): void
    {
        $this->entrarNoPainel('Admin');
        Usuario::factory()->comAcesso()->create(['email' => 'ja@exemplo.com', 'login' => 'jaexiste']);

        Livewire::test(ManageUsuariosDoSite::class)
            ->callAction('create', data: ['nome' => 'Sem dados'])
            ->assertHasActionErrors(['login' => 'required_without', 'email' => 'required_without', 'password' => 'required', 'roles' => 'required']);

        Livewire::test(ManageUsuariosDoSite::class)
            ->callAction('create', data: ['nome' => 'Repetido', 'login' => 'JaExiste', 'email' => 'ja@exemplo.com', 'password' => 'curta', 'roles' => [$this->grupoDoSite('Operador')]])
            ->assertHasActionErrors(['login' => 'unique', 'email' => 'unique', 'password' => 'min']);

        Livewire::test(ManageUsuariosDoSite::class)
            ->callAction('create', data: ['nome' => 'Arroba', 'login' => 'fulano@exemplo', 'password' => 'senha-segura', 'roles' => [$this->grupoDoSite('Operador')]])
            ->assertHasActionErrors(['login' => 'regex']);
    }

    public function test_usuario_sem_email_entra_pelo_usuario(): void
    {
        $this->entrarNoPainel('Admin');

        Livewire::test(ManageUsuariosDoSite::class)
            ->callAction('create', data: [
                'nome' => 'Ciclano Souza',
                'login' => ' CiclanoSouza ',
                'password' => 'senha-segura',
                'roles' => [$this->grupoDoSite('Admin')],
            ])
            ->assertHasNoActionErrors()
            ->assertSee('ciclanosouza');

        $guilherme = Usuario::query()->sole();
        $this->assertSame(['ciclanosouza', null], [$guilherme->login, $guilherme->email]);

        $this->withoutVite()
            ->post('/login', ['login' => 'CiclanoSouza', 'password' => 'senha-segura'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($guilherme, 'web');
    }

    public function test_editar_sem_senha_mantem_a_atual(): void
    {
        $this->entrarNoPainel('Admin');
        $operador = Usuario::factory()->comAcesso()->create(['nome' => 'Paulo', 'apelido' => null]);
        $operador->assignRole('Operador');

        Livewire::test(ManageUsuariosDoSite::class)
            ->callAction(TestAction::make('edit')->table($operador), data: ['nome' => 'Paulo Lima', 'password' => ''])
            ->assertHasNoActionErrors();

        $operador->refresh();
        $this->assertSame('Paulo Lima', $operador->nome);
        $this->assertTrue(Hash::check('password', $operador->password));
    }

    public function test_geral_cadastra_jogador_mas_nao_ve_usuarios_do_site(): void
    {
        $this->entrarNoPainel('Geral');

        $this->get(UsuarioDoSiteResource::getUrl())->assertForbidden();
        $this->assertFalse(UsuarioDoSiteResource::canViewAny());
    }
}
