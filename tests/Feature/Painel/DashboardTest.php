<?php

namespace Tests\Feature\Painel;

use App\Actions\Agenda\SalvarEvento;
use App\Enums\Periodicidade;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Metricas;
use App\Filament\Widgets\ArrecadacaoPorJogo;
use App\Filament\Widgets\Atalhos;
use App\Filament\Widgets\CaixaDoMes;
use App\Filament\Widgets\JogadoresPorJogo;
use App\Filament\Widgets\JogosPorMes;
use App\Filament\Widgets\PrizePoolPorJogo;
use App\Filament\Widgets\ProximoJogo;
use App\Filament\Widgets\Proximos;
use App\Filament\Widgets\RankingEmDestaque;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Ranking;
use App\Models\Usuario;
use App\Services\Jogo\EventoDoJogo;
use App\Services\Painel\Atalhos as ModulosDoPainel;
use App\Services\Painel\Indicadores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

/** Painel de controle: próximo jogo e ranking em destaque, caixa do mês, atalhos dos módulos e agenda. */
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

    public function test_tela_na_ordem(): void
    {
        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('Outubro 2026')
            ->assertSeeInOrder(['Poker Night #20', 'Nenhum ranking valendo agora', 'Arrecadação no mês', 'Atalhos', 'Próximos'])
            ->assertDontSee('Arrecadação por jogo'); // os gráficos foram para as Métricas

        $this->get(Metricas::getUrl())->assertOk()->assertSee(['Arrecadação por jogo', 'Prize pool por jogo', 'Jogos por mês']);
    }

    public function test_proximo_jogo_em_destaque(): void
    {
        Livewire::test(ProximoJogo::class)
            ->assertSee(['Próximo torneio · em 5 dias', 'Poker Night #20', 'Domingo, 25/10/2026 · 20:00'])
            ->assertSeeInOrder(['Confirmados', '2', 'Mesas', '1', 'Buy-in', 'R$ 100', 'Blinds', 'sem estrutura', 'Prize pool estimado', 'R$ 200'])
            ->assertSee(['Editar', 'Abrir no site']);

        // O Geral não vê o prize pool estimado (financeiro.ver).
        $this->entrarNoPainel('Geral');
        Livewire::test(ProximoJogo::class)->assertSee('Confirmados')->assertDontSee('Prize pool estimado');
    }

    public function test_jogo_em_andamento_vem_antes_do_agendado(): void
    {
        $aoVivo = Jogo::factory()->emAndamento()->create(['nome' => 'Cash da quarta', 'data' => '2026-10-19']);

        $this->assertTrue(app(Indicadores::class)->proximoJogo()->is($aoVivo));

        Livewire::test(ProximoJogo::class)
            ->assertSee(['Em andamento', 'Cash da quarta', 'Abrir a noite'])
            ->assertSeeHtml('aria-current="step"')
            ->assertSeeInOrder(['Agendado', 'Rebuys', 'Intervalo', 'Entradas fechadas', 'Finalizado'])
            ->assertSeeInOrder(['Em jogo', '0 de 0', 'Entradas', 'Nível', 'Mesas', 'Arrecadado', 'Prize pool']);

        $this->assertSame([['Agendado', 'Rebuys', 'Intervalo', 'Entradas fechadas', 'Finalizado'], 1], ProximoJogo::etapas($aoVivo));
        $this->assertSame(['Agendado', 'Em andamento', 'Finalizado'], ProximoJogo::etapas(Jogo::factory()->cashGame()->emAndamento()->make())[0]);
    }

    public function test_sem_jogo_agendado(): void
    {
        $this->proximo->forceFill(['status' => 'rascunho'])->save();

        Livewire::test(ProximoJogo::class)
            ->assertSee(['Nenhum jogo agendado', '1 jogo em rascunho: ele entra aqui depois de agendado', 'Ver jogos', 'Novo jogo']);
    }

    public function test_caixa_do_mes(): void
    {
        Livewire::test(CaixaDoMes::class)
            ->assertSee(['Arrecadação no mês', 'R$ 300', 'outubro · 1 jogo'])
            ->assertSee(['Prize pools no mês', 'Aluguéis no mês', '1 aluguel pago'])
            // Caio R$ 100 + aluguel do Felipe R$ 250; o caixa deve 195 + 5 aos jogadores.
            ->assertSee(['Valores pendentes', 'R$ 350', '1 jogador · 1 aluguel a receber · caixa deve R$ 200'])
            ->mountAction('pendencias')
            ->assertMountedActionModalSee(['Caio', 'deve ao caixa', 'Ana', 'a receber do caixa', 'R$ 195', 'Beto', 'R$ 5', 'Felipe', 'Confirmado', 'R$ 250'])
            ->assertMountedActionModalDontSee(['Gustavo', 'Diego']);
    }

    public function test_ranking_em_destaque(): void
    {
        $outubro = Jogo::factory()->finalizado()->create(['data' => '2026-10-04']);
        $setembro = Jogo::factory()->finalizado()->create(['data' => '2026-09-06']);
        $ranking = fn (string $nome, Periodicidade $tipo, ?string $inicio, ?string $fim, bool $ativo = true): Ranking => Ranking::query()
            ->create(['nome' => $nome, 'periodicidade' => $tipo, 'inicio' => $inicio, 'fim' => $fim, 'ativo' => $ativo]);

        $anual = $ranking('Ranking 2026', Periodicidade::Anual, '2026-01-01', '2026-12-31');
        $mensal = $ranking('Mensal de outubro', Periodicidade::Mensal, '2026-10-01', '2026-10-31');
        $acabou = $ranking('Mensal de setembro', Periodicidade::Mensal, '2026-09-01', '2026-09-30'); // já acabou
        $inativo = $ranking('Antigo', Periodicidade::Livre, null, null, false);
        [$diana, $edu, $fabio] = array_map(fn (string $nome) => Usuario::factory()->create(['nome' => $nome, 'apelido' => null]), ['Diana', 'Edu', 'Fábio']);

        foreach ([[$anual, $outubro, $diana, 1, 13], [$anual, $outubro, $edu, 2, 9], [$anual, $outubro, $fabio, 3, 7], [$mensal, $outubro, $edu, 1, 13],
            [$acabou, $setembro, $fabio, 1, 100], [$inativo, $outubro, $fabio, 1, 100]] as [$r, $jogo, $quem, $posicao, $pontos]) {
            $r->pontos()->create(['jogo_id' => $jogo->id, 'usuario_id' => $quem->id, 'posicao' => $posicao, 'pontos' => $pontos]);
        }

        // Dos que valem hoje, o que distribuiu mais pontos (29 contra 13); setembro e o inativo não entram.
        $destaque = app(Indicadores::class)->rankingEmDestaque();
        $this->assertTrue($destaque['ranking']->is($anual));
        $this->assertSame(29, $destaque['pontos']);
        $this->assertSame(['Diana', 'Edu', 'Fábio'], $destaque['classificacao']->pluck('nome')->all());
        $this->assertSame(['Mensal de outubro'], $destaque['outros']->pluck('nome')->all());

        Livewire::test(RankingEmDestaque::class)
            ->assertSee(['Ranking 2026', 'Ranking em destaque · 01/01/2026 a 31/12/2026', 'Classificação'])
            ->assertSeeInOrder(['1º lugar', 'Diana', '1 vitória · 1 jogo', '13 pontos', '2º', 'Edu', '9 pontos', '3º', 'Fábio', '7 pontos'])
            ->assertSee(['Também valendo', 'Mensal de outubro'])
            ->assertDontSee('Mensal de setembro');
    }

    public function test_ranking_sem_pontos_ou_sem_ranking(): void
    {
        Ranking::query()->create(['nome' => 'Novembro', 'periodicidade' => Periodicidade::Mensal, 'inicio' => '2026-11-01', 'fim' => '2026-11-30', 'ativo' => true]);
        $this->assertNull(app(Indicadores::class)->rankingEmDestaque()); // ainda não começou

        Livewire::test(RankingEmDestaque::class)->assertSee('Nenhum ranking valendo agora');

        Ranking::query()->create(['nome' => 'Livre', 'periodicidade' => Periodicidade::Livre, 'ativo' => true]);

        Livewire::test(RankingEmDestaque::class)->assertSee(['Livre', 'sem período', 'Ninguém pontuou ainda']);
    }

    public function test_atalhos_dos_modulos(): void
    {
        $modulos = app(ModulosDoPainel::class)->modulos();

        // Na ordem do menu; o Admin não tem Grupos (só o Desenvolvedor).
        $this->assertSame(['Cadastros', 'Agenda', 'Dashboards e relatórios', 'Sistema'], array_column($modulos, 'grupo'));
        $itens = collect($modulos)->flatMap(fn (array $m) => $m['itens'])->keyBy('rotulo');
        $this->assertSame(['Jogos', 'Modelos', 'Jogadores', 'Mesas', 'Blinds', 'Rankings', 'Temporadas'], array_column($modulos[0]['itens'], 'rotulo'));
        $this->assertSame(['Padrões e premiação', 'Usuários do site', 'Contas do painel'], array_column($modulos[3]['itens'], 'rotulo'));

        $this->assertSame('1 agendado', $itens['Jogos']['contexto']);
        $this->assertSame('1 de 2 ativas', $itens['Mesas']['contexto']);
        $this->assertSame('2 em aberto', $itens['Aluguéis']['contexto']); // Felipe e Gustavo
        $this->assertSame('2 eventos marcados', $itens['Calendário']['contexto']); // de hoje em diante: o #20 e o Gustavo

        Livewire::test(Atalhos::class)
            ->assertSeeInOrder(['Cadastros', 'Jogos', '1 agendado', 'Agenda', 'Calendário', 'Dashboards e relatórios', 'Métricas', 'Sistema', 'Contas do painel']);
    }

    public function test_graficos_nas_metricas_e_proximos(): void
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

    public function test_geral_nao_ve_dinheiro_nem_o_que_nao_abre(): void
    {
        $this->entrarNoPainel('Geral');

        $this->get(Dashboard::getUrl())->assertOk()->assertDontSee(['Arrecadação no mês', 'Valores pendentes']);

        Livewire::test(ProximoJogo::class)->assertSee(['Poker Night #20', 'Ver'])->assertDontSee('Editar');

        $this->assertFalse(CaixaDoMes::canView());
        $this->assertTrue(RankingEmDestaque::canView());
        $this->assertTrue(Proximos::canView());
        $this->assertFalse(ArrecadacaoPorJogo::canView());
        $this->assertTrue(JogadoresPorJogo::canView());

        // Só o que o Geral abre: sem Modelos, Mesas, Temporadas, Aluguéis, relatórios nem Sistema.
        $modulos = app(ModulosDoPainel::class)->modulos();
        $this->assertSame(['Cadastros', 'Agenda'], array_column($modulos, 'grupo'));
        $this->assertSame(['Jogos', 'Jogadores', 'Blinds', 'Rankings'], array_column($modulos[0]['itens'], 'rotulo'));
        $this->assertSame(['Calendário'], array_column($modulos[1]['itens'], 'rotulo'));
    }

    public function test_rotulo_do_jogo(): void
    {
        $this->assertSame('#18', Indicadores::rotulo(new Jogo(['nome' => 'Poker Night #18'])));
        $this->assertSame('04/10', Indicadores::rotulo(new Jogo(['nome' => 'Especial', 'data' => '2026-10-04'])));
    }
}
