<?php

namespace Tests\Feature\Painel;

use App\Filament\Pages\Configuracoes;
use App\Models\RegraPremiacao;
use App\Support\ArredondarPremios;
use App\Support\PadroesDoJogo;
use Database\Seeders\RegrasPremiacaoSeeder;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

class ConfiguracoesTest extends TestCase
{
    use RefreshDatabase, UsaPainel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entrarNoPainel('Admin');
        $this->seed(RegrasPremiacaoSeeder::class);
        Repeater::fake();
    }

    private function regras(array $faixas): array
    {
        return array_map(fn (array $f): array => ['jogadores_min' => $f[0], 'jogadores_max' => $f[1], 'percentuais' => $f[2]], $faixas);
    }

    public function test_abre_com_os_padroes_e_as_regras_do_clube(): void
    {
        $this->get(Configuracoes::getUrl())->assertOk();

        Livewire::test(Configuracoes::class)
            ->assertSchemaStateSet(['horario' => '20:00', 'lugares_por_mesa' => 9, 'arredondar_premios' => true])
            ->assertSet('dados.regras', $this->regras([[1, 10, '65 / 35'], [11, 18, '50 / 30 / 20'], [19, null, '45 / 27 / 17 / 11']]));
    }

    public function test_salva_padroes_e_regras(): void
    {
        Livewire::test(Configuracoes::class)
            ->fillForm([
                'horario' => '19:30', 'lugares_por_mesa' => 8, 'tamanho_mesa_final' => 8,
                'buyin_valor' => '120', 'buyin_fichas' => 25000, 'taxa_nome' => 'Casa', 'taxa_percentual' => '10', 'arredondar_premios' => false,
                'regras' => $this->regras([[1, 9, '60 / 40'], [10, null, '50 / 27,5 / 22,5']]),
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertEquals(['19:30', 8, 120, 25000, 'Casa', 10], array_values(array_intersect_key(PadroesDoJogo::todos(), array_flip([
            'horario', 'lugares_por_mesa', 'buyin_valor', 'buyin_fichas', 'taxa_nome', 'taxa_percentual',
        ]))));
        $this->assertSame([60, 40], RegraPremiacao::query()->paraJogadores(5)->first()->percentuais);
        $this->assertSame([50, 27.5, 22.5], RegraPremiacao::query()->paraJogadores(30)->first()->percentuais);
        $this->assertSame(2, RegraPremiacao::query()->count());
        $this->assertFalse(ArredondarPremios::ativo());
    }

    public function test_regras_erradas_nao_salvam(): void
    {
        $erros = [
            [[[1, 10, '60 / 30']], 'somam 90%'],
            [[[1, 10, '65 / 35'], [12, null, '50 / 30 / 20']], 'tem de começar em 11'],
            [[[3, null, '65 / 35']], 'começar em 1 ou 2'],
            [[[1, 10, '65 / 35'], [11, 18, '50 / 30 / 20']], 'fica sem limite'],
            [[[1, 10, '65 / x']], 'separados por "/"'],
            [[[1, 2, '50 / 30 / 20'], [3, null, '60 / 40']], 'mais posições do que tem jogador'],
        ];

        foreach ($erros as [$faixas, $mensagem]) {
            Livewire::test(Configuracoes::class)
                ->fillForm(['regras' => $this->regras($faixas)])
                ->call('salvar')
                ->assertHasErrors('dados.regras')
                ->assertSee($mensagem);
        }

        $this->assertSame(3, RegraPremiacao::query()->count());
    }

    public function test_geral_nao_entra(): void
    {
        $this->entrarNoPainel('Geral');

        $this->get(Configuracoes::getUrl())->assertForbidden();
    }
}
