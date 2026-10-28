<?php

namespace Tests\Feature\Painel;

use App\Enums\Periodicidade;
use App\Filament\Resources\Rankings\Pages\ClassificacaoRanking;
use App\Filament\Resources\Rankings\Pages\CreateRanking;
use App\Filament\Resources\Rankings\Pages\ListRankings;
use App\Filament\Resources\Rankings\RankingResource;
use App\Filament\Resources\Temporadas\Pages\ManageTemporadas;
use App\Models\Jogo;
use App\Models\Ranking;
use App\Models\Temporada;
use App\Models\Usuario;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

class RankingsTest extends TestCase
{
    use RefreshDatabase, UsaPainel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entrarNoPainel('Admin');
        Repeater::fake();
    }

    public function test_cria_ranking_com_pontos_por_posicao_e_bonus(): void
    {
        Livewire::test(CreateRanking::class)
            ->fillForm([
                'nome' => 'Anual 2026',
                'periodicidade' => Periodicidade::Anual->value,
                'inicio' => '2026-01-01',
                'fim' => '2026-12-31',
                'ativo' => true,
                'pontuacoes' => [['pontos' => 10], ['pontos' => 6], ['pontos' => 4]],
                'ft_pontua' => true,
                'pontos_mesa_final' => 2,
                'participacao_pontua' => true,
                'pontos_participacao' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ranking = Ranking::query()->sole();
        $this->assertSame([1 => 10, 2 => 6, 3 => 4], $ranking->pontuacoes()->pluck('pontos', 'posicao')->all());
        $this->assertSame([true, 2, true, 1], [$ranking->ft_pontua, $ranking->pontos_mesa_final, $ranking->participacao_pontua, $ranking->pontos_participacao]);

        Livewire::test(ListRankings::class)
            ->assertCanSeeTableRecords([$ranking])
            ->assertSee('10 / 6 / 4')
            ->assertSee('mesa final +2 · participação +1');
    }

    public function test_classificacao_soma_os_pontos_do_periodo(): void
    {
        $ranking = Ranking::query()->create(['nome' => 'Outubro', 'periodicidade' => Periodicidade::Mensal, 'inicio' => '2026-10-01', 'fim' => '2026-10-31']);
        [$ana, $beto] = [Usuario::factory()->create(['nome' => 'Ana', 'apelido' => null]), Usuario::factory()->create(['nome' => 'Beto', 'apelido' => null])];
        $dentro1 = Jogo::factory()->finalizado()->create(['data' => '2026-10-04']);
        $dentro2 = Jogo::factory()->finalizado()->create(['data' => '2026-10-11']);
        $fora = Jogo::factory()->finalizado()->create(['data' => '2026-11-01']);

        foreach ([[$dentro1, $ana, 1, 13], [$dentro1, $beto, 2, 9], [$dentro2, $beto, 1, 13], [$dentro2, $ana, 3, 7], [$fora, $ana, 1, 13]] as [$jogo, $quem, $posicao, $pontos]) {
            $ranking->pontos()->create(['jogo_id' => $jogo->id, 'usuario_id' => $quem->id, 'posicao' => $posicao, 'pontos' => $pontos]);
        }

        $this->get(RankingResource::getUrl('classificacao', ['record' => $ranking]))->assertOk();

        // Beto 22 (1 vitória), Ana 20: o jogo de novembro não entra.
        Livewire::test(ClassificacaoRanking::class, ['record' => $ranking->getRouteKey()])
            ->assertCanSeeTableRecords([$beto, $ana], inOrder: true)
            ->assertSeeInOrder(['Beto', '22', '2', '1', '1º', 'Ana', '20', '2', '1', '1º']);
    }

    public function test_empate_vai_para_a_melhor_posicao(): void
    {
        $ranking = Ranking::query()->create(['nome' => 'Livre', 'periodicidade' => Periodicidade::Livre]);
        $jogo = Jogo::factory()->finalizado()->create();
        $quinto = Usuario::factory()->create(['nome' => 'Quinto', 'apelido' => null]);
        $quarto = Usuario::factory()->create(['nome' => 'Quarto', 'apelido' => null]);

        foreach ([[$quinto, 5], [$quarto, 4]] as [$quem, $posicao]) {
            $ranking->pontos()->create(['jogo_id' => $jogo->id, 'usuario_id' => $quem->id, 'posicao' => $posicao, 'pontos' => 3]);
        }

        Livewire::test(ClassificacaoRanking::class, ['record' => $ranking->getRouteKey()])
            ->assertCanSeeTableRecords([$quarto, $quinto], inOrder: true);
    }

    public function test_ranking_com_pontos_nao_se_exclui(): void
    {
        $usado = Ranking::query()->create(['nome' => 'Usado', 'periodicidade' => Periodicidade::Livre]);
        $usado->pontos()->create(['jogo_id' => Jogo::factory()->finalizado()->create()->id, 'usuario_id' => Usuario::factory()->create()->id, 'pontos' => 1]);
        $livre = Ranking::query()->create(['nome' => 'Livre', 'periodicidade' => Periodicidade::Livre]);

        Livewire::test(ListRankings::class)
            ->assertActionHidden(TestAction::make('delete')->table($usado))
            ->callAction(TestAction::make('delete')->table($livre));

        $this->assertSame(['Usado'], Ranking::query()->pluck('nome')->all());
    }

    public function test_temporadas(): void
    {
        Livewire::test(ManageTemporadas::class)
            ->callAction('create', data: ['nome' => '2026/2', 'inicio' => '2026-07-01', 'fim' => '2026-12-31'])
            ->assertHasNoFormErrors();

        $temporada = Temporada::query()->sole();
        Jogo::factory()->create(['temporada_id' => $temporada->id]);

        Livewire::test(ManageTemporadas::class)
            ->assertSee('2026/2')
            ->assertActionHidden(TestAction::make('delete')->table($temporada));
    }

    public function test_geral_ve_rankings_mas_nao_cria(): void
    {
        $this->entrarNoPainel('Geral');

        $this->get(RankingResource::getUrl('index'))->assertOk();
        $this->get(RankingResource::getUrl('create'))->assertForbidden();
    }
}
