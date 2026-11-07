<?php

namespace Tests\Feature\Site;

use App\Enums\StatusJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelecaoDeJogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo('2026-10-11 15:00');
        $this->actingAs(Usuario::factory()->foraDaNoite()->create(), 'web');
    }

    public function test_precisa_estar_logado(): void
    {
        auth('web')->logout();

        $this->get('/jogos')->assertRedirect('/login');
    }

    public function test_abas_mostram_so_o_que_o_site_ve(): void
    {
        Jogo::factory()->agendado()->comEntradasPadrao('150.00')->create(['nome' => 'Poker Night #19', 'data' => '2026-10-11']);
        Jogo::factory()->agendado()->create(['nome' => 'Poker Night #20', 'data' => '2026-10-18']);
        Jogo::factory()->finalizado()->create(['nome' => 'Poker Night #17', 'data' => '2026-09-27']);
        Jogo::factory()->create(['nome' => 'Rascunho secreto']);
        Jogo::factory()->create(['nome' => 'Cancelado'])->forceFill(['status' => StatusJogo::Cancelado])->save();
        Jogo::factory()->modelo()->agendado()->create(['nome' => 'Modelo de sábado']);

        $this->get('/jogos')
            ->assertOk()
            ->assertSee('Escolha o jogo')
            ->assertSeeInOrder(['Poker Night #19', 'Poker Night #20'])
            ->assertSee('R$ 150')
            ->assertSee('Dom 11/10/2026')
            ->assertDontSee('Poker Night #17')
            ->assertDontSee('Rascunho secreto')
            ->assertDontSee('Cancelado')
            ->assertDontSee('Modelo de sábado');

        $this->get('/jogos?aba=finalizados')
            ->assertOk()
            ->assertSee('Poker Night #17')
            ->assertDontSee('Poker Night #20');
    }

    public function test_jogo_de_hoje_em_destaque(): void
    {
        Jogo::factory()->agendado()->create(['nome' => 'Poker Night #19', 'data' => '2026-10-11', 'horario' => '20:00']);

        $this->get('/jogos')
            ->assertSee('Jogo de hoje')
            ->assertSee('Hoje · 20:00');
    }

    public function test_jogo_ao_vivo_tem_prioridade_no_destaque(): void
    {
        $this->seed([RegrasPremiacaoSeeder::class, PokerNight18Seeder::class]);
        Jogo::factory()->agendado()->create(['nome' => 'Poker Night #19', 'data' => '2026-10-11']);

        $this->get('/jogos?aba=andamento')
            ->assertSee('Ao vivo agora')
            ->assertSee('Poker Night #18')
            ->assertSee('10%'); // taxa "Casa"
    }

    public function test_busca_pelo_nome(): void
    {
        Jogo::factory()->agendado()->create(['nome' => 'Poker Night #19', 'data' => '2026-10-12']);
        Jogo::factory()->agendado()->create(['nome' => 'Deepstack de sábado', 'data' => '2026-10-17']);

        $this->get('/jogos?busca=deep')
            ->assertSee('Deepstack de sábado')
            ->assertDontSee('Poker Night #19');

        $this->get('/jogos?busca=nada%25')
            ->assertSee('Nenhum jogo com');
    }
}
