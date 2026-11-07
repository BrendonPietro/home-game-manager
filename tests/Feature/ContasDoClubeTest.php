<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Filament\Auth\Perfil;
use App\Models\Seguranca;
use App\Models\Usuario;
use Database\Seeders\ContasDoClubeSeeder;
use Database\Seeders\PermissoesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** Contas do clube (config/clube.php → ContasDoClubeSeeder) e a troca da própria senha no site e no painel. */
class ContasDoClubeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissoesSeeder::class);

        config([
            'clube.desenvolvedor' => 'Brendon Pietro <Brendon@Exemplo.com>',
            'clube.desenvolvedor_senha' => 'senha-do-dev',
            'clube.admins' => 'Seij <seij@exemplo.com>, diego@exemplo.com',
            'clube.admins_senha' => 'senha-dos-admins',
        ]);
    }

    public function test_ler_a_lista_do_env(): void
    {
        $this->assertSame(
            [['Brendon Pietro', 'brendon@exemplo.com'], ['Diego', 'diego@exemplo.com']],
            ContasDoClubeSeeder::ler(' Brendon Pietro <Brendon@Exemplo.com> , diego@exemplo.com, não-é-email '),
        );
        $this->assertSame([], ContasDoClubeSeeder::ler(null));
    }

    public function test_cria_as_contas_nos_dois_logins_com_o_grupo_certo(): void
    {
        $this->seed(ContasDoClubeSeeder::class);

        $dev = Usuario::query()->where('email', 'brendon@exemplo.com')->sole();
        $painelDoDev = Seguranca::query()->where('login', 'brendon@exemplo.com')->sole();
        $this->assertSame(['Brendon Pietro', true, true], [$dev->nome, $dev->acessa_site, $dev->ativo]);
        $this->assertSame(['Desenvolvedor'], $dev->getRoleNames()->all());
        $this->assertSame(['Desenvolvedor'], $painelDoDev->getRoleNames()->all());
        $this->assertTrue($painelDoDev->can('grupos.editar'));
        $this->assertTrue(Hash::check('senha-do-dev', $dev->password) && Hash::check('senha-do-dev', $painelDoDev->password));

        foreach (['seij@exemplo.com' => 'Seij', 'diego@exemplo.com' => 'Diego'] as $email => $nome) {
            $admin = Usuario::query()->where('email', $email)->sole();
            $this->assertSame([$nome, ['Admin']], [$admin->nome, $admin->getRoleNames()->all()]);
            $this->assertSame(['Admin'], Seguranca::query()->where('login', $email)->sole()->getRoleNames()->all());
            $this->assertTrue(Hash::check('senha-dos-admins', $admin->password));
        }

        // O Desenvolvedor do site tem tudo, até o que o Admin não tiver mais.
        $this->assertSame(
            Usuario::query()->where('email', 'seij@exemplo.com')->sole()->getAllPermissions()->pluck('name')->sort()->values()->all(),
            $dev->getAllPermissions()->pluck('name')->sort()->values()->all(),
        );
    }

    public function test_rodar_de_novo_nao_mexe_na_senha_trocada(): void
    {
        $this->seed(ContasDoClubeSeeder::class);
        Usuario::query()->where('email', 'seij@exemplo.com')->sole()->forceFill(['password' => 'trocada-pelo-seij'])->save();

        $this->seed(ContasDoClubeSeeder::class);

        $this->assertSame(3, Usuario::query()->count());
        $this->assertTrue(Hash::check('trocada-pelo-seij', Usuario::query()->where('email', 'seij@exemplo.com')->value('password')));
    }

    public function test_sem_contas_no_env_nao_cria_nada(): void
    {
        config(['clube.desenvolvedor' => null, 'clube.admins' => '']);

        $this->seed(ContasDoClubeSeeder::class);

        $this->assertSame([0, 0], [Usuario::query()->count(), Seguranca::query()->count()]);
    }

    public function test_entra_no_painel_com_o_email_e_troca_a_senha_no_perfil(): void
    {
        $this->seed(ContasDoClubeSeeder::class);
        Filament::setCurrentPanel('painel');

        Livewire::test(Login::class)
            ->fillForm(['login' => 'brendon@exemplo.com', 'password' => 'senha-do-dev'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $conta = Seguranca::query()->where('login', 'brendon@exemplo.com')->sole();
        $this->assertAuthenticatedAs($conta, 'painel');
        $this->get(Filament::getProfileUrl())->assertOk()->assertSee('Usuário');

        Livewire::test(Perfil::class)
            ->fillForm(['password' => 'nova-senha-123', 'passwordConfirmation' => 'nova-senha-123', 'currentPassword' => 'errada'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        Livewire::test(Perfil::class)
            ->fillForm(['password' => 'nova-senha-123', 'passwordConfirmation' => 'nova-senha-123', 'currentPassword' => 'senha-do-dev'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('nova-senha-123', $conta->fresh()->password));
        $this->assertSame('brendon@exemplo.com', $conta->fresh()->login);
    }

    public function test_troca_a_propria_senha_no_site(): void
    {
        $this->seed(ContasDoClubeSeeder::class);
        $seij = Usuario::query()->where('email', 'seij@exemplo.com')->sole();
        $lembrar = $seij->remember_token;

        $this->actingAs($seij, 'web')
            ->get(route('conta'))
            ->assertOk()
            ->assertSee(['Minha conta', 'seij@exemplo.com', 'TROCAR SENHA']);

        $this->from(route('conta'))->put(route('conta.atualizar'), ['senha_atual' => 'errada', 'senha' => 'nova-senha-123', 'senha_confirmation' => 'nova-senha-123'])
            ->assertSessionHasErrors(['senha_atual' => 'A senha atual não confere.']);
        $this->from(route('conta'))->put(route('conta.atualizar'), ['senha_atual' => 'senha-dos-admins', 'senha' => 'curta', 'senha_confirmation' => 'curta'])
            ->assertSessionHasErrors('senha');
        $this->from(route('conta'))->put(route('conta.atualizar'), ['senha_atual' => 'senha-dos-admins', 'senha' => 'senha-dos-admins', 'senha_confirmation' => 'senha-dos-admins'])
            ->assertSessionHasErrors(['senha' => 'A senha nova precisa ser diferente da atual.']);

        $this->put(route('conta.atualizar'), ['senha_atual' => 'senha-dos-admins', 'senha' => 'nova-senha-123', 'senha_confirmation' => 'nova-senha-123'])
            ->assertRedirect(route('conta'))
            ->assertSessionHas('aviso', 'Senha trocada');

        $this->assertTrue(Hash::check('nova-senha-123', $seij->fresh()->password));
        $this->assertNotSame($lembrar, $seij->fresh()->remember_token);

        // A topbar leva a Minha conta pelo avatar.
        $this->get(route('jogos.index'))->assertSee('href="'.route('conta').'"', false);
    }
}
