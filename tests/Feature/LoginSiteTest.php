<?php

namespace Tests\Feature;

use App\Models\Seguranca;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoginSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function operador(array $dados = []): Usuario
    {
        // Com site.fora_da_noite, para entrar no dashboard sem jogo em andamento (o Operador sem ela: Site\AcessoDoSiteTest).
        return Usuario::factory()->foraDaNoite()->create(['email' => 'operador@exemplo.com', ...$dados]);
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/')->assertRedirect('/login');

        $this->get('/login')
            ->assertOk()
            ->assertSee('Entrar na operação')
            ->assertSee('Usuário ou e-mail')
            ->assertSee('Lembrar acesso')
            ->assertDontSee('fonts.googleapis.com');
    }

    public function test_operador_entra_com_email_e_senha(): void
    {
        $operador = $this->operador(['nome' => 'Marcos Silva', 'apelido' => 'Marcão']);

        $this->post('/login', ['login' => 'operador@exemplo.com', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($operador, 'web');

        // Dashboard com o operador na topbar (o avatar mostra o apelido).
        $this->get('/')
            ->assertOk()
            ->assertSee('title="Marcão"', false)
            ->assertSee('Sair');
    }

    public function test_email_ignora_maiusculas_e_espacos(): void
    {
        $operador = $this->operador();

        $this->post('/login', ['login' => ' Operador@Exemplo.COM ', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($operador, 'web');
    }

    public function test_entra_pelo_usuario_sem_diferenciar_maiusculas(): void
    {
        $operador = $this->operador(['login' => 'MarcosSilva', 'email' => null]);
        $this->assertSame('marcossilva', $operador->login); // gravado em minúsculas

        $this->post('/login', ['login' => 'marcos', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'Usuário ou senha incorretos.']);

        $this->post('/login', ['login' => ' MarcosSilva ', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($operador, 'web');
    }

    public function test_volta_para_a_pagina_pretendida_do_site_mas_nao_do_painel(): void
    {
        $this->operador();

        $this->withSession(['url.intended' => url('/vitrine')])
            ->post('/login', ['login' => 'operador@exemplo.com', 'password' => 'password'])
            ->assertRedirect(url('/vitrine'));

        Auth::guard('web')->logout();

        // Quem tentou abrir o painel antes não vai parar no painel ao entrar no site.
        $this->withSession(['url.intended' => url('/painel/mesas')])
            ->post('/login', ['login' => 'operador@exemplo.com', 'password' => 'password'])
            ->assertRedirect(route('home'));
    }

    public function test_lembrar_acesso_grava_o_cookie(): void
    {
        $this->operador();

        $this->post('/login', ['login' => 'operador@exemplo.com', 'password' => 'password', 'remember' => '1'])
            ->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_senha_errada_nao_entra(): void
    {
        $this->operador();

        $this->from('/login')
            ->post('/login', ['login' => 'operador@exemplo.com', 'password' => 'errada'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['login' => 'Usuário ou senha incorretos.']);

        $this->assertGuest('web');
    }

    public function test_campos_vazios_com_mensagem_em_portugues(): void
    {
        $this->from('/login')
            ->post('/login', ['login' => '', 'password' => ''])
            ->assertSessionHasErrors([
                'login' => 'O campo usuário ou e-mail é obrigatório.',
                'password' => 'O campo senha é obrigatório.',
            ]);
    }

    public function test_quem_nao_acessa_o_site_nao_entra(): void
    {
        $this->operador(['acessa_site' => false]);
        Usuario::factory()->comAcesso()->create(['email' => 'inativo@exemplo.com', 'ativo' => false]);
        Usuario::factory()->comAcesso()->create(['email' => 'excluido@exemplo.com'])->delete();

        foreach (['operador@exemplo.com', 'inativo@exemplo.com', 'excluido@exemplo.com'] as $email) {
            $this->post('/login', ['login' => $email, 'password' => 'password'])
                ->assertSessionHasErrors('login');
        }

        $this->assertGuest('web');
    }

    public function test_bloqueia_depois_de_cinco_tentativas(): void
    {
        $this->operador();

        foreach (range(1, 5) as $tentativa) {
            $this->post('/login', ['login' => 'operador@exemplo.com', 'password' => 'errada']);
        }

        $this->post('/login', ['login' => 'operador@exemplo.com', 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest('web');
        $this->assertStringStartsWith('Muitas tentativas', session('errors')->first('login'));
    }

    public function test_sair(): void
    {
        $this->actingAs($this->operador(), 'web')
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest('web');
    }

    public function test_quem_ja_entrou_nao_ve_o_login(): void
    {
        $this->actingAs($this->operador(), 'web')
            ->get('/login')
            ->assertRedirect('/');
    }

    public function test_conta_do_painel_nao_abre_o_site(): void
    {
        $this->actingAs(Seguranca::factory()->create(), 'painel')
            ->get('/')
            ->assertRedirect('/login');
    }
}
