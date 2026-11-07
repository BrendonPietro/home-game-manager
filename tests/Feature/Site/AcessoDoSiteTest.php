<?php

namespace Tests\Feature\Site;

use App\Models\Jogo;
use App\Models\Usuario;
use Database\Seeders\PermissoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Middleware site.acesso: conta desativada sai na hora; o Operador só fica no site com um jogo em andamento. */
class AcessoDoSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissoesSeeder::class);
    }

    private function conta(string $grupo, array $dados = []): Usuario
    {
        $usuario = Usuario::factory()->comAcesso()->create(['email' => strtolower($grupo).'@exemplo.com', ...$dados]);
        $usuario->assignRole($grupo);

        return $usuario;
    }

    public function test_sessao_dura_a_noite_inteira(): void
    {
        $this->assertGreaterThanOrEqual(8 * 60, config('session.lifetime'));
    }

    public function test_operador_sem_jogo_em_andamento_sai_e_ve_a_pagina_sem_torneio(): void
    {
        Jogo::factory()->agendado()->create();
        Jogo::factory()->finalizado()->create();

        $this->actingAs($this->conta('Operador'), 'web')
            ->get('/')
            ->assertRedirect(route('sem-torneio'));

        $this->assertGuest('web');

        $this->get(route('sem-torneio'))
            ->assertOk()
            ->assertSee('Nenhum torneio em andamento')
            ->assertSee('Entrar de novo');
    }

    public function test_operador_que_entra_sem_jogo_cai_na_pagina_sem_torneio(): void
    {
        $this->conta('Operador');

        $this->followingRedirects()
            ->post('/login', ['email' => 'operador@exemplo.com', 'password' => 'password'])
            ->assertOk()
            ->assertSee('Nenhum torneio em andamento');

        $this->assertGuest('web');
    }

    public function test_operador_com_jogo_em_andamento_vai_direto_para_a_noite(): void
    {
        $noite = Jogo::factory()->emAndamento()->create();
        $finalizado = Jogo::factory()->finalizado()->create();
        $operador = $this->conta('Operador');

        $this->actingAs($operador, 'web')->get('/')->assertRedirect(route('jogos.ao-vivo', $noite));
        $this->get(route('jogos.fechamento', $finalizado))->assertRedirect(route('jogos.ao-vivo', $noite));
        $this->get(route('jogos.index'))->assertRedirect(route('jogos.index', ['aba' => 'andamento']));
        $this->get(route('jogos.index', ['aba' => 'andamento']))->assertOk();
        $this->get(route('jogos.resumo', $noite))->assertOk();

        $this->assertAuthenticatedAs($operador, 'web');
    }

    public function test_quando_o_jogo_acaba_o_operador_sai(): void
    {
        $noite = Jogo::factory()->emAndamento()->create();
        $operador = $this->conta('Operador');

        $this->actingAs($operador, 'web')->get(route('jogos.resumo', $noite))->assertOk();

        $noite->forceFill(['status' => 'finalizado'])->save();

        $this->get(route('jogos.resumo', $noite))->assertRedirect(route('sem-torneio'));
        $this->assertGuest('web');
    }

    public function test_admin_usa_o_site_sem_jogo_em_andamento(): void
    {
        $finalizado = Jogo::factory()->finalizado()->create();

        $this->actingAs($this->conta('Admin'), 'web')->get('/')->assertOk();
        $this->get(route('jogos.index', ['aba' => 'finalizados']))->assertOk()->assertSee($finalizado->nome);
    }

    public function test_conta_desativada_sai_na_hora(): void
    {
        Jogo::factory()->emAndamento()->create();
        $admin = $this->conta('Admin');

        $this->actingAs($admin, 'web')->get('/')->assertOk();

        $admin->forceFill(['ativo' => false])->save();

        $this->get('/')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Seu acesso ao site foi desativado. Fale com o organizador.']);
        $this->assertGuest('web');
    }

    public function test_conta_sem_acesso_ao_site_sai_na_hora(): void
    {
        Jogo::factory()->emAndamento()->create();
        $operador = $this->conta('Operador');

        $this->actingAs($operador, 'web')->get('/')->assertRedirect();

        $operador->forceFill(['acessa_site' => false])->save();

        $this->get('/')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }
}
