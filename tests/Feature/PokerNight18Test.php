<?php

namespace Tests\Feature;

use App\Enums\FaseTorneio;
use App\Enums\StatusJogo;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Models\Entrada;
use App\Models\Jogo;
use App\Support\Dinheiro;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A noite de referência. As contas daqui são feitas à mão, em centavos, direto das entradas,
 * de propósito: a Calculadora (CalculadoraTest) precisa chegar exatamente nos mesmos números.
 */
class PokerNight18Test extends TestCase
{
    use RefreshDatabase;

    private Jogo $jogo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RegrasPremiacaoSeeder::class, PokerNight18Seeder::class]);
        $this->jogo = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
    }

    public function test_cadastro_da_noite(): void
    {
        $jogo = $this->jogo;

        $this->assertSame(StatusJogo::EmAndamento, $jogo->status);
        $this->assertSame(FaseTorneio::Intervalo, $jogo->fase);
        $this->assertSame('2026-10-04', $jogo->data->toDateString());
        $this->assertSame(6, $jogo->nivel_atual);

        $this->assertSame(
            ['Buy-in', 'Rebuy', 'Add-on', 'Buy-in duplo', 'Buy-in triplo'],
            $jogo->itensDeEntrada()->orderBy('ordem')->pluck('nome')->all(),
        );
        $this->assertSame(['50.00', '30.00', '20.00'], $jogo->premiacoes()->orderBy('posicao')->pluck('percentual')->all());

        $mesas = $jogo->mesas()->with(['mesa', 'dealer'])->get();
        $this->assertSame(['Mesa 01', 'Mesa 02'], $mesas->pluck('mesa.nome')->all());
        $this->assertSame(['Sérgio', 'Paulo'], $mesas->pluck('dealer.nome')->all());
        $this->assertSame(['Mesa 01', 'Mesa 02'], $jogo->evento->mesas->pluck('nome')->sort()->values()->all());

        $this->assertSame(12, $jogo->participantes()->count());
        $this->assertSame(12, $jogo->participantes()->where('status', StatusParticipante::Jogando)->count());
        $this->assertSame([7, 5], $mesas->map(fn ($mesa) => $mesa->participantes()->count())->all());
    }

    public function test_entradas_e_numeros_de_referencia(): void
    {
        $entradas = $this->jogo->entradas()->validas()->get();

        $this->assertCount(22, $entradas);
        $this->assertSame(12, $entradas->where('tipo', TipoItem::Buyin)->count());
        $this->assertSame(6, $entradas->where('tipo', TipoItem::Rebuy)->count());
        $this->assertSame(4, $entradas->where('tipo', TipoItem::Addon)->count());

        $arrecadacao = $entradas->sum(fn (Entrada $e) => Dinheiro::centavos($e->valor));
        $premiacaoBruta = $entradas->sum(fn (Entrada $e) => intdiv(Dinheiro::centavos($e->valor) * Dinheiro::centavos($e->percentual_premiacao), 10000));
        $taxas = intdiv($premiacaoBruta * 10, 100); // "Casa" 10%
        $prizePool = $premiacaoBruta - $taxas;

        $this->assertSame(210000, $arrecadacao);
        $this->assertSame(210000, $premiacaoBruta);
        $this->assertTrue($entradas->every(fn (Entrada $e) => $e->percentual_premiacao === '100.00'));
        $this->assertSame(21000, $taxas);
        $this->assertSame(189000, $prizePool);

        $premios = $this->jogo->premiacoes()->orderBy('posicao')->get()
            ->map(fn ($p) => intdiv($prizePool * Dinheiro::centavos($p->percentual), 10000))
            ->all();
        $this->assertSame([94500, 56700, 37800], $premios);
        $this->assertSame($prizePool, array_sum($premios));

        // Arredondando para R$ 5 (padrão do clube): do 2º para baixo desce para o múltiplo e o 1º fica com o resto.
        $arredondados = [0, ...array_map(fn (int $v) => intdiv($v, 500) * 500, array_slice($premios, 1))];
        $arredondados[0] = $prizePool - array_sum($arredondados);
        $this->assertSame([95000, 56500, 37500], $arredondados);
    }

    public function test_investido_por_jogador(): void
    {
        $investido = $this->jogo->participantes()->with('usuario')->get()
            ->mapWithKeys(fn ($p) => [
                $p->usuario->nome_exibicao => Dinheiro::brlCentavos(
                    $p->entradas()->validas()->get()->sum(fn (Entrada $e) => Dinheiro::centavos($e->valor)),
                ),
            ])
            ->sortKeys()
            ->all();

        $this->assertSame([
            'André' => 'R$ 200',
            'Brendon' => 'R$ 250',
            'Bruno' => 'R$ 100',
            'Carlos' => 'R$ 250',
            'Diego' => 'R$ 100',
            'Felipe' => 'R$ 100',
            'João' => 'R$ 150',
            'Lucas' => 'R$ 200',
            'Marcão' => 'R$ 250',
            'Pedro' => 'R$ 200',
            'Rafael' => 'R$ 200',
            'Thiago' => 'R$ 100',
        ], $investido);
    }

    public function test_rodar_de_novo_nao_duplica(): void
    {
        $this->seed(PokerNight18Seeder::class);

        $this->assertSame(1, Jogo::query()->where('nome', PokerNight18Seeder::NOME)->count());
        $this->assertSame(22, Entrada::query()->count());
    }
}
