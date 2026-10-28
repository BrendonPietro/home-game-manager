<?php

namespace Tests\Feature\Painel;

use App\Actions\Agenda\SalvarEvento;
use App\Filament\Pages\Relatorios;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Participante;
use App\Models\Seguranca;
use App\Models\Temporada;
use App\Models\Usuario;
use App\Services\Relatorios\Exportacao;
use App\Services\Relatorios\Filtro;
use App\Services\Relatorios\Relatorios as GeradorDeRelatorios;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\MontaNoite;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

class RelatoriosTest extends TestCase
{
    use MontaNoite, RefreshDatabase, UsaPainel;

    private Temporada $temporada;

    /** @var list<Participante> */
    private array $tres;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-20 15:00'));
        $this->entrarNoPainel('Admin');
        $this->temporada = Temporada::query()->create(['nome' => '2026/2', 'inicio' => '2026-07-01', 'fim' => '2026-12-31']);

        // #19 (11/10) finalizado: Ana 1ª (+95), Beto 2º (+5), Caio 3º (−100). E um agendado em setembro.
        $this->tres = $this->noiteFinalizadaDeTres();
        Jogo::factory()->agendado()->create(['nome' => 'Especial de setembro', 'data' => '2026-09-26']);

        $mesa = Mesa::factory()->create(['nome' => 'Mesa 02']);
        foreach ([['Diego', 'pago', '300', '2026-10-08'], ['Felipe', 'confirmado', '250', '2026-10-15'], ['Igor', 'cancelado', '300', '2026-09-20']] as [$cliente, $status, $valor, $data]) {
            app(SalvarEvento::class)(null, ['tipo' => 'aluguel', 'cliente' => $cliente, 'status' => $status, 'valor' => $valor, 'data' => $data, 'hora_inicio' => '19:00', 'mesas' => [$mesa->id]]);
        }
    }

    private function gerar(string $tipo, array $campos = ['periodo' => 'tudo'])
    {
        return app(GeradorDeRelatorios::class)->gerar($tipo, Filtro::doFormulario($campos));
    }

    public function test_premiacoes_na_temporada_atual(): void
    {
        $this->get(Relatorios::getUrl())->assertOk();

        Livewire::test(Relatorios::class)
            ->assertSet('filtros.periodo', 'temporada:'.$this->temporada->id)
            ->assertSee('Premiações — Temporada 2026/2')
            ->assertSeeInOrder(['Ana', '+R$ 95', 'Beto', '+R$ 5', 'Caio', '−R$ 100'])
            ->assertSee(['Prêmios pagos', 'R$ 300', 'Maior prêmio', 'R$ 195', 'Ana · #19']);

        $linhas = $this->gerar('premiacoes')->linhas;
        $this->assertSame(
            [[1, 'Ana', 1, 10000, 19500, 9500, 1], [2, 'Beto', 1, 10000, 10500, 500, 1], [3, 'Caio', 1, 10000, 0, -10000, 0]],
            array_map('array_values', $linhas),
        );
    }

    public function test_premiacoes_de_um_jogador_por_jogo(): void
    {
        Livewire::test(Relatorios::class)
            ->set('filtros.jogador', $this->tres[0]->usuario_id)
            ->assertSee(['Premiações de Ana', 'Poker Night #19', '1º', '+R$ 95']);
    }

    public function test_jogos_entradas_prize_pools_alugueis_e_arrecadacao(): void
    {
        // Um confirmado sem buy-in conta como confirmado, não como jogador.
        $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create()->id]);

        $jogos = $this->gerar('jogos');
        $this->assertSame([['Poker Night #19', 'Finalizado', 4, 3, 30000], ['Especial de setembro', 'Agendado', 0, 0, 0]], array_map(
            fn (array $l): array => [$l['jogo'], $l['status'], $l['confirmados'], $l['jogadores'], $l['arrecadado']],
            array_reverse($jogos->linhas),
        ));
        $this->assertSame('3', $jogos->resumo[1]['valor']); // jogadores diferentes

        $this->assertSame(['buyins' => 3, 'rebuys' => 0, 'addons' => 0, 'opcionais' => 0, 'total' => 30000], array_slice($this->gerar('entradas')->linhas[0], 2));
        $this->assertSame(['jogadores' => 3, 'arrecadado' => 30000, 'taxas' => 0, 'fora' => 0, 'prize_pool' => 30000], array_slice($this->gerar('prize_pools')->linhas[0], 2));

        $alugueis = $this->gerar('alugueis', ['periodo' => 'tudo', 'jogador' => $this->tres[0]->usuario_id]);
        $this->assertSame(['Igor', 'Diego', 'Felipe'], array_column($alugueis->linhas, 'cliente'));
        $this->assertSame(['R$ 300', 'R$ 250', 'R$ 300', 'R$ 550'], array_column($alugueis->resumo, 'valor'));
        $this->assertSame('Este relatório não usa os filtros de jogo e de jogador.', $alugueis->aviso);

        // Setembro só teve o aluguel cancelado e o jogo agendado: não entra.
        $this->assertSame([['Out/2026', 1, 30000, 30000, 0, 0, 30000, 30000]], array_map('array_values', $this->gerar('arrecadacao')->linhas));

        Livewire::test(Relatorios::class)
            ->call('escolher', 'alugueis')
            ->assertSet('relatorio', 'alugueis')
            ->assertSee(['Aluguéis — Temporada 2026/2', 'Felipe', 'Confirmado', 'A receber']);
    }

    public function test_periodos(): void
    {
        $periodo = fn (array $campos): array => [
            ($f = Filtro::doFormulario($campos))->de?->toDateString(), $f->ate?->toDateString(), $f->periodo,
        ];

        $this->assertSame(['2026-10-01', '2026-10-31', 'Outubro 2026'], $periodo(['periodo' => 'mes']));
        $this->assertSame(['2026-09-01', '2026-09-30', 'Setembro 2026'], $periodo(['periodo' => 'mes_anterior']));
        $this->assertSame(['2026-09-21', '2026-10-20', 'Últimos 30 dias'], $periodo(['periodo' => '30_dias']));
        $this->assertSame(['2026-07-01', '2026-12-31', 'Temporada 2026/2'], $periodo(['periodo' => 'temporada:'.$this->temporada->id]));
        $this->assertSame(['2026-10-01', null, 'Desde 01/10/2026'], $periodo(['periodo' => 'personalizado', 'de' => '2026-10-01']));
        $this->assertSame([null, null, 'Tudo'], $periodo(['periodo' => 'tudo']));

        // Outubro deixa o jogo de setembro de fora.
        $this->assertSame(['Poker Night #19'], array_column($this->gerar('jogos', ['periodo' => 'mes'])->linhas, 'jogo'));

        $this->temporada->update(['fim' => '2026-09-30']);
        $this->assertSame('ano', Filtro::periodoPadrao());
    }

    public function test_exporta_csv_e_excel(): void
    {
        $relatorio = $this->gerar('premiacoes');

        $csv = app(Exportacao::class)->csv($relatorio);
        $this->assertStringStartsWith("\xEF\xBB\xBF#;Jogador;Jogos;Investido;Premiação;Resultado;ITM\n", $csv);
        $this->assertStringContainsString("1;Ana;1;100,00;195,00;95,00;1\n", $csv);
        $this->assertStringContainsString("3;Caio;1;100,00;0,00;-100,00;0\n", $csv);

        $caminho = tempnam(sys_get_temp_dir(), 'teste');
        app(Exportacao::class)->xlsx($relatorio, $caminho);
        $reader = new Reader;
        $reader->open($caminho);
        $linhas = [];
        foreach ($reader->getSheetIterator() as $planilha) {
            foreach ($planilha->getRowIterator() as $linha) {
                $linhas[] = $linha->toArray();
            }
        }
        $reader->close();
        unlink($caminho);

        $this->assertSame('Premiações · Tudo', $linhas[0][0]);
        $this->assertSame(['#', 'Jogador', 'Jogos', 'Investido', 'Premiação', 'Resultado', 'ITM'], $linhas[1]);
        $this->assertEquals([1, 'Ana', 1, 100, 195, 95, 1], $linhas[2]);

        Livewire::test(Relatorios::class)
            ->callAction('exportarCsv')
            ->assertFileDownloaded('relatorio-premiacoes-2026-10-20.csv')
            ->callAction('exportarExcel')
            ->assertFileDownloaded('relatorio-premiacoes-2026-10-20.xlsx');
    }

    public function test_pdf_e_a_pagina_para_imprimir_com_os_filtros(): void
    {
        $ana = $this->tres[0]->usuario_id;
        $url = route('filament.painel.relatorios.imprimir', [
            'relatorio' => 'premiacoes', 'periodo' => 'temporada:'.$this->temporada->id, 'jogador' => $ana, 'imprimir' => 1,
        ]);

        Livewire::test(Relatorios::class)
            ->set('filtros.jogador', $ana)
            ->assertActionHasUrl('exportarPdf', $url)
            ->assertActionShouldOpenUrlInNewTab('exportarPdf');

        $this->get($url)
            ->assertOk()
            ->assertSee(['Premiações de Ana — Temporada 2026/2', 'Jogador: Ana', 'Poker Night #19', '1º', '+R$ 95', 'size: A4 portrait'])
            ->assertSee('setTimeout(() => window.print(), 300)', false);

        // Sem imprimir=1 a página não abre o diálogo; arrecadação (8 colunas) sai deitada.
        $this->get(route('filament.painel.relatorios.imprimir', ['relatorio' => 'arrecadacao', 'periodo' => 'tudo']))
            ->assertOk()
            ->assertSee(['Arrecadação — Tudo', 'Out/2026', 'size: A4 landscape'])
            ->assertDontSee('setTimeout(() => window.print(), 300)', false);

        $this->get(route('filament.painel.relatorios.imprimir', ['relatorio' => 'nada']))->assertNotFound();
    }

    public function test_quem_ve_e_quem_exporta(): void
    {
        $imprimir = route('filament.painel.relatorios.imprimir', ['relatorio' => 'jogos']);

        $soVer = Seguranca::factory()->create();
        $soVer->givePermissionTo('relatorios.ver');
        $this->actingAs($soVer, 'painel');

        $this->get(Relatorios::getUrl())->assertOk();
        Livewire::test(Relatorios::class)
            ->assertActionHidden('exportarCsv')
            ->assertActionHidden('exportarExcel')
            ->assertActionHidden('exportarPdf');
        $this->get($imprimir)->assertForbidden();

        $this->entrarNoPainel('Geral');
        $this->get(Relatorios::getUrl())->assertForbidden();
        $this->get($imprimir)->assertForbidden();

        auth('painel')->logout();
        $this->get($imprimir)->assertRedirect(Filament::getLoginUrl());
    }
}
