<?php

namespace Tests\Feature\Painel;

use App\Actions\Jogo\CriarDeModelo;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Filament\Pages\Metricas;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Jogo\CopiaDoJogo;
use App\Services\Relatorios\Filtro;
use App\Services\Relatorios\Metricas as GeradorDeMetricas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

/** Dashboards e relatórios → Métricas: médias dos finalizados e as comparações por evento, modalidade e buy-in. */
class MetricasTest extends TestCase
{
    use RefreshDatabase;
    use UsaPainel;

    /**
     * Jogo finalizado com `$jogadores` buy-ins e `$rebuys` rebuys (entradas gravadas direto: aqui só importam os números).
     */
    private function noite(string $data, int $jogadores, int $rebuys, string $buyin = '100.00', ?Jogo $modelo = null, bool $cash = false): Jogo
    {
        $fabrica = Jogo::factory()->comEntradasPadrao($buyin);
        $jogo = ($cash ? $fabrica->cashGame() : $fabrica)->create(['data' => $data, 'modelo_id' => $modelo?->id]);
        $jogo->forceFill(['status' => StatusJogo::Finalizado])->save();
        $item = fn (TipoItem $tipo) => $jogo->itens()->where('tipo', $tipo)->first();

        for ($i = 0; $i < $jogadores; $i++) {
            $participante = $jogo->participantes()->create(['usuario_id' => Usuario::factory()->create()->id]);
            $lancar = fn (TipoItem $tipo) => $jogo->entradas()->create([
                'uuid' => (string) str()->uuid(), 'participante_id' => $participante->id, 'jogo_item_id' => $item($tipo)->id,
                'tipo' => $tipo, 'valor' => $item($tipo)->valor, 'percentual_premiacao' => 100,
            ]);
            $lancar(TipoItem::Buyin);

            if ($i < $rebuys) {
                $lancar(TipoItem::Rebuy);
            }
        }

        return $jogo;
    }

    public function test_medias_e_comparacoes(): void
    {
        $sabado = Jogo::factory()->modelo('Torneio de sábado')->create();
        $deep = Jogo::factory()->modelo('Deepstack')->create();

        // Sábado: 10 e 8 jogadores, buy-in R$ 100, 4 e 0 rebuys. Deepstack: 6 jogadores, R$ 200, 6 rebuys.
        $this->noite('2026-09-05', 10, 4, modelo: $sabado);
        $this->noite('2026-10-03', 8, 0, modelo: $sabado);
        $this->noite('2026-10-17', 6, 6, '200.00', $deep);
        $this->noite('2026-10-24', 4, 0, '50.00', cash: true); // cash game, sem modelo
        Jogo::factory()->emAndamento()->create(['data' => '2026-10-31']); // não finalizado: não conta

        $m = app(GeradorDeMetricas::class)->gerar(Filtro::doFormulario(['periodo' => 'tudo']));
        $g = $m['geral'];

        // 28 jogadores; arrecadação 1.400 + 800 + 2.400 + 200 = 4.800.
        $this->assertSame([4, 28, 7.0, 480000], [$g['jogos'], $g['jogadores'], $g['mediaJogadores'], $g['arrecadacao']]);
        $this->assertSame(120000, $g['mediaArrecadacao']);
        $this->assertSame(17100, $g['gastoPorJogador']); // 4.800 ÷ 28 = 171,43 → R$ 171 (médias em reais)
        $this->assertSame(0.36, $g['rebuysPorJogador']); // 10 ÷ 28

        // Por tipo de evento, do maior gasto por jogador para o menor; Deepstack gasta 134% acima da média.
        $porModelo = collect($m['porModelo'])->keyBy('grupo');
        $this->assertSame(['Deepstack', 'Torneio de sábado', 'Sem modelo'], array_column($m['porModelo'], 'grupo'));
        $this->assertSame([40000, 134], [$porModelo['Deepstack']['gastoPorJogador'], $porModelo['Deepstack']['diferenca']]);
        $this->assertSame([2, 9.0, 12200, -29], [
            $porModelo['Torneio de sábado']['jogos'], $porModelo['Torneio de sábado']['mediaJogadores'],
            $porModelo['Torneio de sábado']['gastoPorJogador'], $porModelo['Torneio de sábado']['diferenca'],
        ]);

        $this->assertSame(['Torneio', 'Cash game'], array_column($m['porModalidade'], 'grupo'));
        $this->assertSame(['Buy-in R$ 50', 'Buy-in R$ 100', 'Buy-in R$ 200'], array_column($m['porBuyin'], 'grupo'));
        $this->assertSame(['Set/26', 'Out/26'], array_column($m['porMes'], 'rotulo'));
        $this->assertSame([1, 3], array_column($m['porMes'], 'jogos'));
    }

    public function test_jogo_lembra_o_modelo_ao_criar_e_ao_duplicar(): void
    {
        $this->entrarNoPainel('Admin');
        $modelo = Jogo::factory()->modelo('Deepstack')->comEntradasPadrao()->create();

        $jogo = app(CriarDeModelo::class)($modelo, 'Deepstack #1', '2026-11-20');
        $this->assertSame($modelo->id, $jogo->modelo_id);

        $copia = app(CopiaDoJogo::class)->copiar($jogo, ['nome' => 'Deepstack #2']);
        $this->assertSame($modelo->id, $copia->modelo_id);

        $novoModelo = app(CopiaDoJogo::class)->copiar($jogo, ['nome' => 'X', 'nome_modelo' => 'Deepstack 2', 'e_modelo' => true]);
        $this->assertNull($novoModelo->modelo_id);
    }

    public function test_tela_de_metricas(): void
    {
        $this->entrarNoPainel('Admin');
        $sabado = Jogo::factory()->modelo('Torneio de sábado')->create();
        $this->noite('2026-10-03', 8, 2, modelo: $sabado);

        $this->get(Metricas::getUrl())->assertOk();

        Livewire::test(Metricas::class)
            ->assertSee(['Jogos finalizados', 'Média de jogadores', 'Gasto por jogador', 'Por tipo de evento', 'Torneio de sábado', 'Pelo valor do buy-in', 'mês a mês'])
            ->assertSee('R$ 125') // (8 × 100 + 2 × 100) ÷ 8
            ->set('filtros.periodo', 'personalizado')
            ->set('filtros.de', '2026-11-01')
            ->assertSee('Nenhum jogo finalizado');

        // Sem relatorios.ver (Geral), a tela não abre.
        $this->entrarNoPainel('Geral');
        $this->get(Metricas::getUrl())->assertForbidden();
    }
}
