<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\EncerrarRebuys;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\RegistrarEntrada;
use App\Enums\DestinoItem;
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

    private function opcional(string $nome, int $valor, array $compravelCom, DestinoItem $destino, bool $obrigatorio = false): Item
    {
        return $this->jogo->itens()->create([
            'tipo' => TipoItem::Opcional,
            'nome' => $nome,
            'valor' => $valor,
            'fichas' => 20000,
            'compravel_com' => $compravelCom,
            'destino' => $destino,
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
        $this->assertSame(['100.00', 20000, DestinoItem::Premiacao, $this->operador->id], [$buyin->valor, $buyin->fichas, $buyin->destino, $buyin->registrado_por]);

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

        $this->assertRegra('Add-on só no intervalo, depois de encerrar os rebuys.', fn () => $this->entrar($joao, TipoItem::Addon));

        $this->entrar($joao, TipoItem::Rebuy);
        $this->entrar($joao, TipoItem::Rebuy); // rebuy sem limite

        app(EncerrarRebuys::class)($this->jogo, $this->admin);

        $this->assertRegra('Os rebuys já foram encerrados.', fn () => $this->entrar($joao, TipoItem::Rebuy));
        $this->entrar($joao, TipoItem::Addon);
        $this->assertRegra('João já comprou Add-on (1× no máximo).', fn () => $this->entrar($joao, TipoItem::Addon));
        $this->entrar($this->jogador('Zeca'), TipoItem::Buyin); // buy-in atrasado ainda vale no intervalo

        app(FecharEntradas::class)($this->jogo, $this->admin);

        $this->assertRegra('As entradas já foram fechadas.', fn () => $this->entrar($lucas, TipoItem::Addon));
        $this->assertSame(6, $this->jogo->entradas()->count());
    }

    public function test_opcionais_junto_da_entrada_e_por_jogador(): void
    {
        $opcional = $this->opcional('Opcional', 10, ['buyin', 'rebuy', 'addon'], DestinoItem::Metade);
        $churrasco = $this->opcional('Churrasco', 30, ['jogador'], DestinoItem::Fora);
        $caixinha = $this->opcional('Caixinha', 5, ['buyin', 'rebuy'], DestinoItem::Fora, obrigatorio: true);
        $soAddon = $this->opcional('Fichas do add-on', 10, ['addon'], DestinoItem::Premiacao);
        $nomes = fn ($entrada) => $entrada->opcionais()->with('item')->get()->pluck('item.nome')->sort()->values()->all();

        $joao = $this->jogador('João');
        $buyin = $this->entrar($joao, TipoItem::Buyin, [$opcional->id, $churrasco->id]);

        $this->assertSame(['Caixinha', 'Churrasco', 'Opcional'], $nomes($buyin));
        $this->assertSame(DestinoItem::Metade, $buyin->opcionais()->where('jogo_item_id', $opcional->id)->sole()->destino);

        // Caixinha obrigatória vem de novo; churrasco é 1 por jogador.
        $rebuy = $this->entrar($joao, TipoItem::Rebuy);
        $this->assertSame(['Caixinha'], $nomes($rebuy));
        $this->assertRegra('João já comprou Churrasco.', fn () => $this->entrar($joao, TipoItem::Rebuy, [$churrasco->id]));
        $this->assertRegra('Esse opcional não é vendido junto de Rebuy.', fn () => $this->entrar($joao, TipoItem::Rebuy, [$soAddon->id]));

        // Opcional por jogador pode ser comprado sozinho; o de entrada, não.
        $lucas = $this->jogador('Lucas');
        $this->assertNull($this->entrar($lucas, $churrasco)->vinculada_a);
        $this->assertRegra('Lucas já comprou Churrasco.', fn () => $this->entrar($lucas, $churrasco));
        $this->assertRegra('Opcional vai junto de um buy-in, rebuy ou add-on.', fn () => $this->entrar($lucas, $opcional));

        $this->assertSame(7, $this->jogo->entradas()->count());
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
