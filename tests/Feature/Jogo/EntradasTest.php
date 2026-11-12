<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\RegistrarEntrada;
use App\Enums\TipoItem;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class EntradasTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNoite();
    }

    private function opcional(string $nome, int $valor, array $compravelCom, int $percentual, bool $obrigatorio = false): Item
    {
        return $this->jogo->itens()->create([
            'tipo' => TipoItem::Opcional,
            'nome' => $nome,
            'valor' => $valor,
            'fichas' => 20000,
            'compravel_com' => $compravelCom,
            'percentual_premiacao' => $percentual,
            'obrigatorio' => $obrigatorio,
        ]);
    }

    public function test_buyin_copia_o_item_e_o_toque_duplo_nao_repete(): void
    {
        $joao = $this->jogador('João');
        $uuid = (string) Str::uuid();

        $buyin = $this->entrar($joao, TipoItem::Buyin, quem: $this->operador, uuid: $uuid);
        $repetida = $this->entrar($joao, TipoItem::Buyin, uuid: $uuid);

        $this->assertTrue($repetida->is($buyin));
        $this->assertSame(1, $this->jogo->entradas()->count());
        $this->assertSame(['100.00', 20000, '100.00', $this->operador->id], [$buyin->valor, $buyin->fichas, $buyin->percentual_premiacao, $buyin->registrado_por]);

        // O valor fica o da hora do lançamento.
        $this->item(TipoItem::Buyin)->update(['valor' => 150]);
        $this->assertSame('100.00', $buyin->fresh()->valor);
    }

    public function test_um_buyin_por_jogador_e_rebuy_pede_buyin(): void
    {
        $joao = $this->jogador('João');
        $duplo = $this->jogo->itens()->create(['tipo' => TipoItem::Buyin, 'nome' => 'Buy-in duplo', 'valor' => 200, 'fichas' => 40000]);

        $this->assertRegra('João ainda não tem buy-in.', fn () => $this->entrar($joao, TipoItem::Rebuy));

        $this->entrar($joao, TipoItem::Buyin);

        $this->assertRegra('João já tem buy-in. Para mais fichas, rebuy.', fn () => $this->entrar($joao, $duplo));

        $duplo->update(['ativo' => false]);
        $this->assertRegra('Buy-in duplo está desativado neste jogo.', fn () => $this->entrar($this->jogador('Lucas'), $duplo));
    }

    public function test_entradas_seguem_as_fases_do_torneio(): void
    {
        $joao = $this->jogador('João');
        $lucas = $this->jogador('Lucas');
        $this->entrar($joao, TipoItem::Buyin);
        $this->entrar($lucas, TipoItem::Buyin);

        $this->assertRegra('Add-on só no intervalo: aperte ABRIR INTERVALO.', fn () => $this->entrar($joao, TipoItem::Addon));

        $this->entrar($joao, TipoItem::Rebuy);
        $this->entrar($joao, TipoItem::Rebuy); // rebuy sem limite

        app(AbrirIntervalo::class)($this->jogo, $this->admin);

        $this->entrar($lucas, TipoItem::Rebuy); // o rebuy continua no intervalo
        $this->entrar($joao, TipoItem::Addon);
        $this->assertRegra('João já comprou Add-on (1× no máximo).', fn () => $this->entrar($joao, TipoItem::Addon));
        $this->entrar($this->jogador('Zeca'), TipoItem::Buyin); // buy-in atrasado ainda vale no intervalo

        app(FecharEntradas::class)($this->jogo, $this->admin);

        $this->assertRegra('As entradas já foram fechadas.', fn () => $this->entrar($lucas, TipoItem::Addon));
        $this->assertRegra('As entradas já foram fechadas.', fn () => $this->entrar($lucas, TipoItem::Rebuy));
        $this->assertSame(7, $this->jogo->entradas()->count());
    }

    public function test_opcionais_junto_da_entrada_e_por_jogador(): void
    {
        $opcional = $this->opcional('Opcional', 10, ['buyin', 'rebuy', 'addon'], 50);
        $churrasco = $this->opcional('Churrasco', 30, ['jogador'], 0);
        $caixinha = $this->opcional('Caixinha', 5, ['buyin', 'rebuy'], 0, obrigatorio: true);
        $soAddon = $this->opcional('Fichas do add-on', 10, ['addon'], 100);
        $nomes = fn ($entrada) => $entrada->opcionais()->with('item')->get()->pluck('item.nome')->sort()->values()->all();

        $joao = $this->jogador('João');
        $buyin = $this->entrar($joao, TipoItem::Buyin, [$opcional->id, $churrasco->id]);

        $this->assertSame(['Caixinha', 'Churrasco', 'Opcional'], $nomes($buyin));
        $this->assertSame('50.00', $buyin->opcionais()->where('jogo_item_id', $opcional->id)->sole()->percentual_premiacao);

        // Caixinha obrigatória vem de novo; churrasco é 1 por jogador.
        $rebuy = $this->entrar($joao, TipoItem::Rebuy);
        $this->assertSame(['Caixinha'], $nomes($rebuy));
        $this->assertRegra('João já comprou Churrasco.', fn () => $this->entrar($joao, TipoItem::Rebuy, [$churrasco->id]));
        $this->assertRegra('Esse opcional não é vendido junto de Rebuy.', fn () => $this->entrar($joao, TipoItem::Rebuy, [$soAddon->id]));

        // Opcional por jogador pode ser comprado sozinho; o de entrada, só com uma entrada para ir junto.
        $lucas = $this->jogador('Lucas');
        $this->assertNull($this->entrar($lucas, $churrasco)->vinculada_a);
        $this->assertRegra('Lucas já comprou Churrasco.', fn () => $this->entrar($lucas, $churrasco));
        $this->assertRegra('Lucas ainda não tem buy-in.', fn () => $this->entrar($lucas, $opcional));

        $this->assertSame(7, $this->jogo->entradas()->count());
    }

    public function test_opcional_de_entrada_comprado_sozinho_vai_um_por_entrada(): void
    {
        $opcional = $this->opcional('Opcional', 10, ['buyin', 'rebuy', 'addon'], 50);
        $jaTem = 'Ana já comprou Opcional para cada entrada: depois de um rebuy ou add-on, dá para comprar de novo.';

        // Sentou (o buy-in nasce sem a escolha dos opcionais): o opcional se compra sozinho, um para o buy-in.
        $ana = $this->jogador('Ana');
        $this->entrar($ana, TipoItem::Buyin);
        $sozinho = $this->entrar($ana, $opcional);

        $this->assertNull($sozinho->vinculada_a);
        $this->assertSame(['10.00', 20000, '50.00'], [$sozinho->valor, $sozinho->fichas, $sozinho->percentual_premiacao]);
        $this->assertSame(': entregar 20.000 fichas', RegistrarEntrada::aEntregar($sozinho));
        $this->assertRegra($jaTem, fn () => $this->entrar($ana, $opcional));

        // Cada rebuy abre mais um, comprado sozinho ou marcado junto do rebuy.
        $this->entrar($ana, TipoItem::Rebuy);
        $this->entrar($ana, $opcional);
        $this->assertRegra($jaTem, fn () => $this->entrar($ana, $opcional));
        $this->entrar($ana, TipoItem::Rebuy, [$opcional->id]);
        $this->assertRegra($jaTem, fn () => $this->entrar($ana, $opcional));
        $this->assertSame(3, $ana->entradas()->validas()->where('jogo_item_id', $opcional->id)->count());

        // O comprado sozinho é uma ação dele: desfazer tira só o opcional e devolve a vez.
        $rebuy = $this->entrar($ana, TipoItem::Rebuy);
        $this->entrar($ana, $opcional);
        $this->assertSame('Opcional de Ana desfeito.', $this->desfazer($ana));
        $this->assertNull($rebuy->fresh()->cancelada_em);
        $this->entrar($ana, $opcional);
        $this->assertSame(4, $ana->entradas()->validas()->where('jogo_item_id', $opcional->id)->count());
    }

    public function test_so_com_o_jogo_em_andamento_e_com_permissao(): void
    {
        $agendado = Jogo::factory()->agendado()->comEntradasPadrao()->create();
        $participante = $agendado->participantes()->create(['usuario_id' => Usuario::factory()->create()->id]);

        $this->assertRegra(
            'Entradas só com o jogo em andamento.',
            fn () => app(RegistrarEntrada::class)($participante, $agendado->itens()->first(), $this->admin, (string) Str::uuid()),
        );

        $this->expectException(AuthorizationException::class);
        $this->entrar($this->jogador('João'), TipoItem::Buyin, quem: Usuario::factory()->comAcesso()->create());
    }
}
