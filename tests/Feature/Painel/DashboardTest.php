<?php

namespace Tests\Feature\Painel;

use App\Actions\Agenda\SalvarEvento;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ArrecadacaoPorJogo;
use App\Filament\Widgets\JogadoresPorJogo;
use App\Filament\Widgets\JogosPorMes;
use App\Filament\Widgets\PrizePoolPorJogo;
use App\Filament\Widgets\Proximos;
use App\Filament\Widgets\VisaoGeral;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Usuario;
use App\Services\Jogo\EventoDoJogo;
use App\Services\Painel\Indicadores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use MontaNoite, RefreshDatabase, UsaPainel;

    private Jogo $proximo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-20 15:00'));
        $this->entrarNoPainel('Admin');

        // #19 (11/10) finalizado: o caixa deve 195 à Ana e 5 ao Beto; Caio deve 100.
        $this->noiteFinalizadaDeTres();

        // #20 (25/10) agendado com 2 confirmados.
        $this->proximo = Jogo::factory()->agendado()->comEntradasPadrao()->create(['nome' => 'Poker Night #20', 'data' => '2026-10-25', 'horario' => '20:00']);
        $this->proximo->mesas()->create(['mesa_id' => Mesa::query()->sole()->id, 'lugares' => 9, 'ordem' => 1]);
        Usuario::factory()->count(2)->create()->each(fn (Usuario $u) => $this->proximo->participantes()->create(['usuario_id' => $u->id]));
        app(EventoDoJogo::class)->sincronizar($this->proximo);

        // Aluguéis: pago em outubro, confirmado que já passou (pendente) e reservado no futuro.
        $mesa = Mesa::factory()->create(['nome' => 'Mesa 02', 'ativa' => false]);
        foreach ([['Diego', 'pago', '300', '2026-10-08'], ['Felipe', 'confirmado', '250', '2026-10-15'], ['Gustavo', 'reservado', '300', '2026-10-30']] as [$cliente, $status, $valor, $data]) {
            app(SalvarEvento::class)(null, ['tipo' => 'aluguel', 'cliente' => $cliente, 'status' => $status, 'valor' => $valor, 'data' => $data, 'hora_inicio' => '19:00', 'mesas' => [$mesa->id]]);
        }
    }

    public function test_numeros_do_mes_e_proximo_jogo(): void
    {
        $this->get(Dashboard::getUrl())->assertOk()->assertSee('Outubro 2026');

        Livewire::test(VisaoGeral::class)
            ->assertSee(['Próximo torneio', 'Poker Night #20', 'Dom · 20:00 · 2 confirmados · 1 mesa', 'Abrir no site'])
            ->assertSee(['Jogos no mês', 'outubro · 1 finalizado'])
            ->assertSee(['Mesas disponíveis', '1 / 2', 'Mesa 02 inativa'])
            ->assertSee(['Arrecadação no mês', 'R$ 300', 'outubro · 1 jogo'])
            ->assertSee(['Prize pools no mês', 'Aluguéis no mês', '1 aluguel pago'])
            // Caio R$ 100 + aluguel do Felipe R$ 250; o caixa deve 195 + 5 aos jogadores.
            ->assertSee(['Valores pendentes', 'R$ 350', '1 jogador · 1 aluguel a receber · caixa deve R$ 200'])
            ->mountAction('pendencias')
            ->assertMountedActionModalSee(['Caio', 'deve ao caixa', 'Ana', 'a receber do caixa', 'R$ 195', 'Beto', 'R$ 5', 'Felipe', 'Confirmado', 'R$ 250'])
            ->assertMountedActionModalDontSee(['Gustavo', 'Diego']);
    }

    public function test_jogo_em_andamento_vem_antes_do_agendado(): void
    {
        $aoVivo = Jogo::factory()->emAndamento()->create(['nome' => 'Cash da quarta', 'data' => '2026-10-19']);

        $this->assertTrue(app(Indicadores::class)->proximoJogo()->is($aoVivo));

        Livewire::test(VisaoGeral::class)->assertSee(['Em andamento', 'Cash da quarta']);
    }

    public function test_graficos_e_proximos(): void
    {
        Livewire::test(ArrecadacaoPorJogo::class)
            ->assertSee(['Arrecadação por jogo', '#19', 'Poker Night #19: R$ 300']);

        Livewire::test(PrizePoolPorJogo::class)
            ->assertSee(['100% da arrecadação', 'Poker Night #19: R$ 300']);

        Livewire::test(JogadoresPorJogo::class)
            ->assertSee('Poker Night #19: 3');

        Livewire::test(JogosPorMes::class)
            ->assertSeeInOrder(['Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out'])
            ->assertSee('Out: 2');

        // De hoje (20/10) em diante: o #20 e o aluguel do Gustavo; os que já passaram não.
        Livewire::test(Proximos::class)
            ->assertSeeInOrder(['Poker Night #20', 'Torneio · 20:00', 'Mesa alugada — Gustavo', 'Aluguel · 19:00 · Mesa 02'])
            ->assertDontSee(['Felipe', 'Poker Night #19']);
    }

    public function test_geral_nao_ve_dinheiro(): void
    {
        $this->entrarNoPainel('Geral');

        $this->get(Dashboard::getUrl())->assertOk();

        Livewire::test(VisaoGeral::class)
            ->assertSee(['Poker Night #20', 'Ver', 'Jogadores cadastrados'])
            ->assertDontSee(['Arrecadação no mês', 'Valores pendentes']);

        $this->assertFalse(ArrecadacaoPorJogo::canView());
        $this->assertFalse(PrizePoolPorJogo::canView());
        $this->assertTrue(JogadoresPorJogo::canView());
        $this->assertTrue(Proximos::canView());
    }

    public function test_rotulo_do_jogo(): void
    {
        $this->assertSame('#18', Indicadores::rotulo(new Jogo(['nome' => 'Poker Night #18'])));
        $this->assertSame('04/10', Indicadores::rotulo(new Jogo(['nome' => 'Especial', 'data' => '2026-10-04'])));
    }
}
