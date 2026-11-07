<?php

namespace Tests\Feature;

use App\Enums\DirecaoPagamento;
use App\Enums\FormaPagamento;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Models\Entrada;
use App\Models\Grupo;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Pagamento;
use App\Models\Ranking;
use App\Models\RegraPremiacao;
use App\Models\Seguranca;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SegurancaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Confere que migrations, models e seeders conversam entre si.
 * As regras de dinheiro ficam no CalculadoraTest.
 */
class ModeloDeDadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_grupos_e_permissoes_por_guard(): void
    {
        $this->assertSame(
            ['Admin', 'Desenvolvedor', 'Geral'],
            Grupo::where('guard_name', 'painel')->orderBy('name')->pluck('name')->all(),
        );
        $this->assertSame(
            ['Admin', 'Desenvolvedor', 'Operador'],
            Grupo::where('guard_name', 'web')->orderBy('name')->pluck('name')->all(),
        );

        // Fora do ambiente local o seed não cria as contas dev; aqui elas vêm do seeder delas.
        $this->assertFalse(Seguranca::where('login', 'dev')->exists());
        $this->seed(SegurancaSeeder::class);
        $dev = Seguranca::where('login', 'dev')->firstOrFail();
        $this->assertTrue($dev->hasRole('Desenvolvedor'));
        $this->assertTrue($dev->can('grupos.editar'));

        $operador = Usuario::factory()->comAcesso()->create();
        $operador->assignRole('Operador');
        $this->assertTrue($operador->can('entradas.registrar'));
        $this->assertFalse($operador->can('taxas.editar'));

        $admin = Usuario::factory()->comAcesso()->create();
        $admin->assignRole('Admin');
        $this->assertTrue($admin->can('estrutura.editar'));
    }

    public function test_regra_de_premiacao_por_faixa(): void
    {
        $this->assertSame([65, 35], RegraPremiacao::paraJogadores(8)->first()->percentuais);
        $this->assertSame([50, 30, 20], RegraPremiacao::paraJogadores(12)->first()->percentuais);
        $this->assertSame([45, 27, 17, 11], RegraPremiacao::paraJogadores(40)->first()->percentuais);
    }

    public function test_torneio_com_entradas_opcionais_taxas_e_lancamentos(): void
    {
        $jogo = Jogo::factory()->comEntradasPadrao()->create();
        $this->assertSame(StatusJogo::Rascunho, $jogo->fresh()->status);
        $this->assertCount(3, $jogo->itensDeEntrada);

        // Variação (rebuy duplo) e opcionais do sábado.
        $jogo->itens()->create(['tipo' => TipoItem::Rebuy, 'nome' => 'Rebuy duplo', 'valor' => 200, 'fichas' => 40000]);
        $opcional = $jogo->itens()->create([
            'tipo' => TipoItem::Opcional, 'nome' => 'Opcional', 'valor' => 10, 'fichas' => 20000,
            'percentual_premiacao' => 50, 'compravel_com' => ['buyin', 'rebuy', 'addon'],
        ]);
        $churrasco = $jogo->itens()->create([
            'tipo' => TipoItem::Opcional, 'nome' => 'Churrasco', 'valor' => 30, 'fichas' => 20000,
            'percentual_premiacao' => 0, 'compravel_com' => [Item::COMPRAVEL_POR_JOGADOR],
        ]);
        $jogo->taxas()->createMany([
            ['nome' => 'Casa', 'calculo' => 'fixo', 'valor' => 190],
            ['nome' => 'Dealers', 'calculo' => 'por_unidade', 'valor' => 215, 'quantidade' => 2],
        ]);

        $this->assertCount(4, $jogo->itensDeEntrada()->get());
        $this->assertCount(2, $jogo->opcionais()->get());
        $this->assertTrue($opcional->compravelCom(TipoItem::Rebuy));
        $this->assertTrue($churrasco->porJogador());
        $this->assertSame('50.00', $opcional->percentual_premiacao);
        $this->assertSame('50% na premiação', $opcional->textoDaPremiacao());

        // Entrada também escolhe o percentual (100% quando não diz), e nunca é obrigatória nem vai junto.
        $rebuy = $jogo->itens()->create(['tipo' => TipoItem::Rebuy, 'nome' => 'X', 'valor' => 1, 'percentual_premiacao' => 75, 'obrigatorio' => true]);
        $this->assertSame(['75.00', false], [$rebuy->fresh()->percentual_premiacao, $rebuy->fresh()->obrigatorio]);
        $this->assertSame('100.00', $jogo->itens()->create(['tipo' => TipoItem::Buyin, 'nome' => 'Y', 'valor' => 1])->fresh()->percentual_premiacao);

        // Mesa, participante, entrada com opcional vinculado, pagamento.
        $jogoMesa = $jogo->mesas()->create(['nome' => 'Mesa 1', 'lugares' => 9]);
        $participante = $jogo->participantes()->create([
            'usuario_id' => Usuario::factory()->create()->id,
            'jogo_mesa_id' => $jogoMesa->id,
            'lugar' => 1,
        ]);

        $buyin = $jogo->itensDeEntrada()->where('tipo', TipoItem::Buyin)->first();
        $entrada = $this->lancar($participante, $buyin);
        $this->lancar($participante, $opcional, $entrada);

        Pagamento::create([
            'uuid' => (string) Str::uuid(),
            'jogo_id' => $jogo->id,
            'participante_id' => $participante->id,
            'direcao' => DirecaoPagamento::JogadorPagou,
            'valor' => 50,
            'forma' => FormaPagamento::Pix,
        ]);

        $this->assertCount(1, $entrada->opcionais);
        $this->assertSame('110.00', number_format((float) $participante->entradas()->validas()->sum('valor'), 2, '.', ''));
        $this->assertSame(1, $jogoMesa->participantes()->count());

        // Desfazer = marcar, nunca apagar.
        $entrada->forceFill(['cancelada_em' => now()])->save();
        $this->assertSame(1, $jogo->entradas()->validas()->count());
        $this->assertSame(2, $jogo->entradas()->count());
        $this->assertSame(1, $jogo->pagamentos()->validas()->count());
    }

    public function test_modelo_fica_fora_dos_jogos_reais_e_rankings_multiplos(): void
    {
        Jogo::factory()->modelo()->create();
        $jogo = Jogo::factory()->cashGame()->create();

        $this->assertSame(1, Jogo::reais()->count());
        $this->assertSame(1, Jogo::modelos()->count());
        $this->assertTrue($jogo->eCashGame());

        $anual = Ranking::create(['nome' => '2026', 'periodicidade' => 'anual', 'ft_pontua' => true, 'pontos_mesa_final' => 5]);
        $mensal = Ranking::create(['nome' => 'Outubro', 'periodicidade' => 'mensal']);
        $anual->pontuacoes()->createMany([
            ['posicao' => 1, 'pontos' => 100],
            ['posicao' => 2, 'pontos' => 70],
            ['posicao' => 3, 'pontos' => 50],
        ]);

        $jogo->rankings()->attach([$anual->id, $mensal->id]);

        $this->assertCount(2, $jogo->rankings);
        $this->assertSame([1, 2, 3], $anual->pontuacoes->pluck('posicao')->all());
    }

    private function lancar($participante, Item $item, ?Entrada $vinculadaA = null): Entrada
    {
        return Entrada::create([
            'uuid' => (string) Str::uuid(),
            'jogo_id' => $participante->jogo_id,
            'participante_id' => $participante->id,
            'jogo_item_id' => $item->id,
            'tipo' => $item->tipo,
            'valor' => $item->valor,
            'fichas' => $item->fichas,
            'percentual_premiacao' => $item->percentual_premiacao,
            'vinculada_a' => $vinculadaA?->id,
        ]);
    }
}
