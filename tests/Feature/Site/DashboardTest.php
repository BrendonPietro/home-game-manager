<?php

namespace Tests\Feature\Site;

use App\Enums\CalculoTaxa;
use App\Enums\TipoItem;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Jogo\EventoDoJogo;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RegrasPremiacaoSeeder::class);
        $this->travelTo('2026-10-07 15:00');
        $this->actingAs(Usuario::factory()->comAcesso()->create(['nome' => 'Operador']), 'web');
    }

    private function confirmar(Jogo $jogo, int $quantos): void
    {
        foreach (range(1, $quantos) as $i) {
            $jogo->participantes()->create(['usuario_id' => Usuario::factory()->create()->id]);
        }
    }

    public function test_sem_jogos(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Nenhum jogo agendado')
            ->assertSee('Nada na agenda.')
            ->assertSee('Nenhuma noite finalizada ainda.')
            ->assertSee('Jogos'); // link na topbar
    }

    public function test_jogo_ao_vivo_aparece_com_os_numeros_reais(): void
    {
        $this->seed(PokerNight18Seeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('Acontecendo agora')
            ->assertSee('Poker Night #18')
            ->assertSee('Ao vivo')
            ->assertSee('12 jogadores')
            ->assertSee('R$ 1.890')
            ->assertSee('Nível 6 · 100 / 200')
            ->assertSee('Domingo, 04/10/2026 · 20:00');
    }

    public function test_proximo_agendado_com_prize_pool_estimado(): void
    {
        $jogo = Jogo::factory()->agendado()->comEntradasPadrao('100.00')->create([
            'nome' => 'Poker Night #19',
            'data' => '2026-10-11',
            'horario' => '20:00',
        ]);
        $jogo->taxas()->create(['nome' => 'Casa', 'calculo' => CalculoTaxa::Percentual, 'valor' => 10]);
        $this->confirmar($jogo, 3);
        app(EventoDoJogo::class)->sincronizar($jogo);

        // Rascunho e cancelado não aparecem, nem no destaque nem na agenda.
        $rascunho = Jogo::factory()->create(['nome' => 'Especial Halloween', 'data' => '2026-10-09']);
        app(EventoDoJogo::class)->sincronizar($rascunho);

        $this->get('/')
            ->assertOk()
            ->assertSee('Próximo torneio')
            ->assertSee('Poker Night #19')
            ->assertSee('3 jogadores')
            ->assertSee('Prize pool estimado')
            ->assertSee('R$ 270') // 3 × 100 − 10%
            ->assertSee('Começa em 4 dias')
            ->assertSee('Próximo') // na agenda
            ->assertDontSee('Especial Halloween');
    }

    public function test_ultimos_eventos_com_campeao_e_ultima_noite(): void
    {
        $noite = Jogo::factory()->finalizado()->create(['nome' => 'Poker Night #17', 'data' => '2026-09-27']);
        $buyin = $noite->itens()->create(['tipo' => TipoItem::Buyin, 'nome' => 'Buy-in', 'valor' => 100, 'padrao' => true]);
        $joao = Usuario::factory()->create(['nome' => 'João', 'apelido' => null]);
        $campeao = $noite->participantes()->create(['usuario_id' => $joao->id]);
        $campeao->forceFill(['posicao_final' => 1])->save();
        $noite->entradas()->create([
            'uuid' => (string) Str::uuid(), 'participante_id' => $campeao->id, 'jogo_item_id' => $buyin->id,
            'tipo' => TipoItem::Buyin, 'valor' => 100, 'destino' => 'premiacao',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Poker Night #17')
            ->assertSee('27/09/2026')
            ->assertSee('João')
            ->assertSee('Finalizado')
            ->assertSee('Última noite')
            ->assertSee('R$ 100');
    }
}
