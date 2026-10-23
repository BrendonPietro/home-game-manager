<?php

namespace Tests\Feature;

use App\Enums\CalculoTaxa;
use App\Enums\DestinoItem;
use App\Enums\MetodoPremiacao;
use App\Enums\TipoItem;
use App\Models\Entrada;
use App\Models\Jogo;
use App\Services\Jogo\Calculadora;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CalculadoraTest extends TestCase
{
    use RefreshDatabase;

    private Calculadora $calculadora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RegrasPremiacaoSeeder::class);
        $this->calculadora = app(Calculadora::class);
    }

    /** @return Collection<int, array{tipo: TipoItem, valor: int, destino: DestinoItem}> */
    private function lancamentos(TipoItem $tipo, int $centavos, int $vezes, DestinoItem $destino = DestinoItem::Premiacao): Collection
    {
        return collect(array_fill(0, $vezes, ['tipo' => $tipo, 'valor' => $centavos, 'destino' => $destino]));
    }

    public function test_poker_night_18(): void
    {
        $this->seed(PokerNight18Seeder::class);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();

        $resumo = $this->calculadora->doJogo($noite);

        $this->assertSame(210000, $resumo->arrecadacao);
        $this->assertSame(210000, $resumo->premiacaoBruta);
        $this->assertSame([['nome' => 'Casa', 'valor' => 21000]], $resumo->taxas);
        $this->assertSame(189000, $resumo->prizePool);
        $this->assertSame(0, $resumo->foraDaPremiacao);
        $this->assertSame(22, $resumo->entradas);
        $this->assertSame(12, $resumo->jogadores);
        $this->assertSame([94500, 56700, 37800], array_column($resumo->premios, 'valor'));
        $this->assertSame(['50.00', '30.00', '20.00'], array_column($resumo->premios, 'percentual'));
        $this->assertSame(56700, $resumo->premio(2));
        $this->assertSame(0, $resumo->premio(4));
    }

    public function test_entrada_cancelada_nao_conta(): void
    {
        $this->seed(PokerNight18Seeder::class);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();

        $noite->entradas()->where('tipo', TipoItem::Addon)->first()->forceFill(['cancelada_em' => now()])->save();

        $resumo = $this->calculadora->doJogo($noite);
        $this->assertSame(205000, $resumo->arrecadacao);
        $this->assertSame(21, $resumo->entradas);
        $this->assertSame(184500, $resumo->prizePool);
    }

    /** O exemplo do torneio de sábado em docs/regras.md. */
    public function test_exemplo_do_sabado(): void
    {
        $jogo = Jogo::factory()->create();
        $jogo->taxas()->createMany([
            ['nome' => 'Casa', 'calculo' => CalculoTaxa::Fixo, 'valor' => 190],
            ['nome' => 'Dealers', 'calculo' => CalculoTaxa::PorUnidade, 'valor' => 215, 'quantidade' => 2],
        ]);

        // Entradas: 16 × 110 + 14 × 100 + 1 × 50 = R$ 3.210 (o doc só dá os totais).
        $lancamentos = collect()
            ->merge($this->lancamentos(TipoItem::Buyin, 11000, 16))
            ->merge($this->lancamentos(TipoItem::Rebuy, 10000, 14))
            ->merge($this->lancamentos(TipoItem::Addon, 5000, 1))
            ->merge($this->lancamentos(TipoItem::Opcional, 1000, 60, DestinoItem::Metade))
            ->merge($this->lancamentos(TipoItem::Opcional, 3000, 16, DestinoItem::Fora));

        $resumo = $this->calculadora->calcular($jogo->fresh(), $lancamentos, 16);

        $this->assertSame(429000, $resumo->arrecadacao);   // R$ 4.290
        $this->assertSame(351000, $resumo->premiacaoBruta); // 3.210 + 300 do opcional
        $this->assertSame(62000, $resumo->totalTaxas);      // casa 190 + dealers 2 × 215
        $this->assertSame(289000, $resumo->prizePool);      // R$ 2.890
        $this->assertSame(78000, $resumo->foraDaPremiacao); // 300 do opcional + 480 do churrasco
        $this->assertSame(31, $resumo->entradas);           // opcionais não contam como entrada
    }

    public function test_taxas_por_jogador_por_entrada_e_percentual_arredondado(): void
    {
        $jogo = Jogo::factory()->create();
        $jogo->taxas()->createMany([
            ['nome' => 'Fichas', 'calculo' => CalculoTaxa::PorJogador, 'valor' => 10],
            ['nome' => 'Baralho', 'calculo' => CalculoTaxa::PorEntrada, 'valor' => 5],
            ['nome' => 'Casa', 'calculo' => CalculoTaxa::Percentual, 'valor' => '7.5'],
        ]);

        $lancamentos = $this->lancamentos(TipoItem::Buyin, 10100, 4)->merge($this->lancamentos(TipoItem::Rebuy, 10100, 2));

        $resumo = $this->calculadora->calcular($jogo->fresh(), $lancamentos, 4);

        $this->assertSame(60600, $resumo->premiacaoBruta);
        // 4 jogadores × 10 · 6 entradas × 5 · 7,5% de 606 = 45,45
        $this->assertSame([4000, 3000, 4545], array_column($resumo->taxas, 'valor'));
        $this->assertSame(60600 - 11545, $resumo->prizePool);
    }

    public function test_sobra_do_arredondamento_vai_para_o_primeiro(): void
    {
        $jogo = Jogo::factory()->create(); // regra do clube: até 10 jogadores → 65/35

        $resumo = $this->calculadora->calcular($jogo, $this->lancamentos(TipoItem::Buyin, 101, 1), 5);

        // 65% de 1,01 = 0,6565 e 35% = 0,3535: 0,65 + 0,35 e o centavo que sobra vai para o 1º.
        $this->assertSame([66, 35], array_column($resumo->premios, 'valor'));
        $this->assertSame(101, array_sum(array_column($resumo->premios, 'valor')));
    }

    public function test_premiacao_manual(): void
    {
        $jogo = Jogo::factory()->create(['metodo_premiacao' => MetodoPremiacao::Manual]);
        $jogo->premiacoes()->createMany([
            ['posicao' => 1, 'percentual' => 60],
            ['posicao' => 2, 'percentual' => 40],
        ]);

        $resumo = $this->calculadora->calcular($jogo->fresh(), $this->lancamentos(TipoItem::Buyin, 10000, 20), 20);

        $this->assertSame([120000, 80000], array_column($resumo->premios, 'valor'));
    }

    public function test_simulacao_pelo_cadastro(): void
    {
        $this->seed(PokerNight18Seeder::class);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();

        // 12 confirmados com o buy-in padrão (R$ 100), sem rebuy: 1.200 − 10% = 1.080.
        $estimado = $this->calculadora->simular($noite, 12);
        $this->assertSame(120000, $estimado->arrecadacao);
        $this->assertSame(108000, $estimado->prizePool);
        $this->assertSame([54000, 32400, 21600], array_column($estimado->premios, 'valor'));

        // Com 6 rebuys e 4 add-ons a simulação chega na noite real (o buy-in duplo do Rafael à parte).
        $this->assertSame(200000, $this->calculadora->simular($noite, 12, 6, 4)->arrecadacao);
    }

    public function test_simulacao_conta_opcionais_obrigatorios(): void
    {
        $jogo = Jogo::factory()->comEntradasPadrao('100.00')->create();
        $jogo->itens()->createMany([
            ['tipo' => TipoItem::Opcional, 'nome' => 'Churrasco', 'valor' => 30, 'destino' => DestinoItem::Fora, 'obrigatorio' => true, 'compravel_com' => ['jogador']],
            ['tipo' => TipoItem::Opcional, 'nome' => 'Opcional', 'valor' => 10, 'destino' => DestinoItem::Metade, 'obrigatorio' => true, 'compravel_com' => ['buyin', 'rebuy', 'addon']],
        ]);

        $resumo = $this->calculadora->simular($jogo, 10, 5);

        // 10 buy-ins + 5 rebuys = 1.500; churrasco 10 × 30 = 300; opcional 15 × 10 = 150.
        $this->assertSame(195000, $resumo->arrecadacao);
        $this->assertSame(150000 + 7500, $resumo->premiacaoBruta);
        $this->assertSame(30000 + 7500, $resumo->foraDaPremiacao);
    }

    public function test_cash_game_nao_tem_premios(): void
    {
        $jogo = Jogo::factory()->cashGame()->create();

        $resumo = $this->calculadora->calcular($jogo, $this->lancamentos(TipoItem::Buyin, 20000, 6), 6);

        $this->assertSame(120000, $resumo->prizePool);
        $this->assertSame([], $resumo->premios);
    }

    public function test_do_jogo_ignora_jogador_sem_buy_in(): void
    {
        $this->seed(PokerNight18Seeder::class);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();

        // Cancelar o buy-in do Bruno (só tem buy-in): ele deixa de contar como jogador.
        $bruno = $noite->participantes()->whereRelation('usuario', 'nome', 'Bruno')->sole();
        Entrada::query()->where('participante_id', $bruno->id)->update(['cancelada_em' => now()]);

        $this->assertSame(11, $this->calculadora->doJogo($noite)->jogadores);
    }
}
