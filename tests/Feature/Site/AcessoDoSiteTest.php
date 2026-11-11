<?php

namespace Tests\Feature\Site;

use App\Models\Jogo;
use App\Models\Usuario;
use App\Support\JanelaDoSite;
use Database\Seeders\PermissoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Middleware site.acesso: conta desativada sai na hora; quem não é admin só vê o torneio dentro da janela
 * (3 h antes do início a 3 h depois do término previsto) e, fora dela, continua logado em "Nenhum torneio agora".
 */
class AcessoDoSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissoesSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-25 12:00'));
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

    public function test_janela_de_3_horas_antes_ate_3_horas_depois_do_termino(): void
    {
        $jogo = Jogo::factory()->agendado()->make(['data' => '2026-10-25', 'horario' => '20:00', 'horario_fim' => '23:30']);

        $this->assertSame('2026-10-25 17:00', JanelaDoSite::abre($jogo)->format('Y-m-d H:i'));
        $this->assertSame('2026-10-26 02:30', JanelaDoSite::fecha($jogo)->format('Y-m-d H:i'));

        // Término antes do início = no dia seguinte; sem término, 12 h depois do início (+ 3 h).
        $jogo->horario_fim = '02:00';
        $this->assertSame('2026-10-26 05:00', JanelaDoSite::fecha($jogo)->format('Y-m-d H:i'));
        $jogo->horario_fim = null;
        $this->assertSame('2026-10-26 11:00', JanelaDoSite::fecha($jogo)->format('Y-m-d H:i'));

        $this->assertFalse(JanelaDoSite::aberta($jogo, Carbon::parse('2026-10-25 16:59')));
        $this->assertTrue(JanelaDoSite::aberta($jogo, Carbon::parse('2026-10-25 17:00')));
        $this->assertTrue(JanelaDoSite::aberta($jogo, Carbon::parse('2026-10-26 11:00')));
        $this->assertFalse(JanelaDoSite::aberta($jogo, Carbon::parse('2026-10-26 11:01')));

        // Rascunho e cancelado nunca abrem; em andamento fica aberto até finalizar, mesmo passando da hora.
        $this->assertFalse(JanelaDoSite::aberta(Jogo::factory()->make(['data' => '2026-10-25', 'horario' => '13:00'])));
        $this->assertTrue(JanelaDoSite::aberta(Jogo::factory()->emAndamento()->make(['data' => '2026-10-20', 'horario' => '20:00'])));
    }

    public function test_antes_da_janela_o_operador_continua_logado_e_ve_quando_abre(): void
    {
        Jogo::factory()->agendado()->create(['nome' => 'Poker Night #20', 'data' => '2026-10-25', 'horario' => '20:00']);
        Jogo::factory()->finalizado()->create(['data' => '2026-10-11', 'horario' => '20:00']);
        $operador = $this->conta('Operador');

        $this->actingAs($operador, 'web')->get('/')->assertRedirect(route('sem-torneio'));

        $this->get(route('sem-torneio'))
            ->assertOk()
            ->assertSee(['Nenhum torneio agora', 'O próximo é Poker Night #20, domingo, 25/10/2026 às 20:00. O site abre para você hoje às 17:00', 'ATUALIZAR', 'Sair']);

        $this->assertAuthenticatedAs($operador, 'web');
    }

    public function test_operador_que_entra_antes_da_janela_fica_logado_esperando(): void
    {
        $this->conta('Operador');

        $this->followingRedirects()
            ->post('/login', ['login' => 'operador@exemplo.com', 'password' => 'password'])
            ->assertOk()
            ->assertSee(['Nenhum torneio agora', 'Quando o próximo torneio for marcado, o site abre 3 horas antes do início']);

        $this->assertAuthenticated('web');
    }

    public function test_3_horas_antes_o_operador_entra_no_torneio_agendado(): void
    {
        $jogo = Jogo::factory()->agendado()->create(['data' => '2026-10-25', 'horario' => '20:00']);
        $outro = Jogo::factory()->agendado()->create(['data' => '2026-11-01', 'horario' => '20:00']);
        $this->actingAs($this->conta('Operador'), 'web');

        $this->travelTo(Carbon::parse('2026-10-25 17:00'));

        $this->get('/')->assertRedirect(route('jogos.conferencia', $jogo));
        $this->get(route('sem-torneio'))->assertRedirect(route('jogos.conferencia', $jogo));
        $this->get(route('jogos.conferencia', $outro))->assertRedirect(route('jogos.conferencia', $jogo));
        $this->get(route('jogos.conferencia', $jogo))->assertOk();
    }

    public function test_operador_com_jogo_em_andamento_vai_direto_para_a_noite(): void
    {
        $noite = Jogo::factory()->emAndamento()->create(['data' => '2026-10-24', 'horario' => '20:00']);
        $finalizado = Jogo::factory()->finalizado()->create(['data' => '2026-10-11']);
        $operador = $this->conta('Operador');

        $this->actingAs($operador, 'web')->get('/')->assertRedirect(route('jogos.ao-vivo', $noite));
        $this->get(route('jogos.fechamento', $finalizado))->assertRedirect(route('jogos.ao-vivo', $noite));
        $this->get(route('jogos.index'))->assertRedirect(route('jogos.index', ['aba' => 'andamento']));
        $this->get(route('jogos.index', ['aba' => 'andamento']))->assertOk();
        $this->get(route('jogos.resumo', $noite))->assertOk();

        $this->assertAuthenticatedAs($operador, 'web');
    }

    public function test_depois_de_finalizar_fica_aberto_ate_3_horas_depois_do_termino(): void
    {
        $noite = Jogo::factory()->emAndamento()->create(['data' => '2026-10-25', 'horario' => '08:00', 'horario_fim' => '11:00']);
        $operador = $this->conta('Operador');

        $this->actingAs($operador, 'web')->get(route('jogos.resumo', $noite))->assertOk();

        $noite->forceFill(['status' => 'finalizado', 'finalizado_em' => now()])->save();

        $this->get(route('jogos.fechamento', $noite))->assertOk(); // 12:00, fecha às 14:00

        $this->travelTo(Carbon::parse('2026-10-25 14:01'));

        $this->get(route('jogos.fechamento', $noite))->assertRedirect(route('sem-torneio'));
        $this->assertAuthenticatedAs($operador, 'web');
    }

    public function test_admin_usa_o_site_a_qualquer_hora(): void
    {
        $finalizado = Jogo::factory()->finalizado()->create(['data' => '2026-10-11']);

        $this->actingAs($this->conta('Admin'), 'web')->get('/')->assertOk();
        $this->get(route('jogos.index', ['aba' => 'finalizados']))->assertOk()->assertSee($finalizado->nome);
        $this->get(route('sem-torneio'))->assertRedirect(route('home'));
    }

    public function test_conta_desativada_sai_na_hora(): void
    {
        Jogo::factory()->emAndamento()->create();
        $admin = $this->conta('Admin');

        $this->actingAs($admin, 'web')->get('/')->assertOk();

        $admin->forceFill(['ativo' => false])->save();

        $this->get('/')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['login' => 'Seu acesso ao site foi desativado. Fale com o organizador.']);
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

    public function test_abrir_no_site_sem_login_passa_pelo_login_e_volta_para_o_jogo(): void
    {
        $noite = Jogo::factory()->emAndamento()->create();
        $this->conta('Admin');

        $this->get(route('jogos.ao-vivo', $noite))->assertRedirect(route('login'));

        $this->post('/login', ['login' => 'admin@exemplo.com', 'password' => 'password'])
            ->assertRedirect(route('jogos.ao-vivo', $noite));
    }
}
