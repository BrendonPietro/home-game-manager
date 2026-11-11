<?php

namespace Tests\Feature\Jogo;

use App\Actions\Jogo\AbrirIntervalo;
use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\SortearLugares;
use App\Enums\StatusParticipante;
use App\Enums\TipoItem;
use App\Livewire\Jogo\AoVivo;
use App\Livewire\Jogo\Sorteio;
use App\Models\JogoMesa;
use App\Models\Participante;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/**
 * Sorteio de quem foi escolhido nas mesas escolhidas (SortearLugares e o modal Jogadores), com o
 * torneio em andamento: quem chegou atrasado, quem voltou com rebuy e quebra de mesa.
 */
class SorteioTest extends TestCase
{
    use MontaNoite, RefreshDatabase;

    private JogoMesa $mesa2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->montarNoite();
        $this->mesa2 = $this->jogo->mesas()->create(['nome' => 'Mesa 02', 'lugares' => 9, 'ordem' => 2]);
    }

    /** Confirmado que chegou e ainda não tem lugar. */
    private function chegou(string $nome, bool $pagouAntes = false): Participante
    {
        $participante = $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => $nome, 'apelido' => null])->id]);
        $participante->forceFill(['pagou_antes' => $pagouAntes])->save();

        return $participante;
    }

    /** @param  list<Participante>  $quem */
    private function sortear(array $quem, ?array $mesas = null): array
    {
        return app(SortearLugares::class)($this->jogo, $this->admin, array_map(fn (Participante $p) => $p->id, $quem), $mesas);
    }

    public function test_quem_chegou_senta_nas_mesas_escolhidas_e_entra_com_o_buyin(): void
    {
        $this->jogo->update(['antecipado_fichas' => 20000]);
        $ana = $this->jogador('Ana');
        $beto = $this->chegou('Beto', pagouAntes: true);
        $caio = $this->chegou('Caio');
        $dani = $this->chegou('Dani'); // não foi escolhida

        $sorteados = $this->sortear([$beto, $caio], [$this->mesa2->id]);

        $this->assertCount(2, $sorteados);
        foreach ([$beto, $caio] as $quem) {
            $quem->refresh();
            $this->assertSame([$this->mesa2->id, StatusParticipante::Jogando], [$quem->jogo_mesa_id, $quem->status]);
            $this->assertSame(1, $quem->entradas()->validas()->where('tipo', TipoItem::Buyin)->count());
        }

        // O resultado diz onde e o buy-in (com o bônus de quem pagou antes).
        $doBeto = collect($sorteados)->firstWhere('participante.id', $beto->id);
        $this->assertSame([20000, 20000], [$doBeto['buyin']->fichas, $doBeto['buyin']->antecipado]);

        // Quem não foi escolhido continua onde estava.
        $this->assertSame([$this->mesa->id, 1], [$ana->fresh()->jogo_mesa_id, $ana->fresh()->lugar]);
        $this->assertNull($dani->fresh()->jogo_mesa_id);
    }

    public function test_as_mesas_ficam_equilibradas_contando_quem_ja_esta_sentado(): void
    {
        foreach (range(1, 5) as $i) {
            $this->jogador("Sentado {$i}");
        }
        $chegaram = array_map(fn (int $i) => $this->chegou("Chegou {$i}"), range(1, 4));

        $this->sortear($chegaram);

        // Mesa 01 com 5 e Mesa 02 vazia: os 4 vão para a Mesa 02 (4/9 < 5/9).
        $this->assertSame(4, $this->mesa2->participantes()->count());
        $this->assertSame(5, $this->mesa->participantes()->count());
    }

    public function test_quem_ja_estava_sentado_e_escolhido_troca_de_lugar_sem_novo_buyin(): void
    {
        $ana = $this->jogador('Ana');
        $this->entrar($ana, TipoItem::Buyin);

        $sorteados = $this->sortear([$ana], [$this->mesa2->id]);

        $this->assertSame($this->mesa2->id, $ana->fresh()->jogo_mesa_id);
        $this->assertNull($sorteados[0]['buyin']);
        $this->assertSame(1, $ana->entradas()->validas()->count());
    }

    public function test_o_lugar_de_quem_caiu_conta_como_livre(): void
    {
        $this->mesa->update(['lugares' => 2]);
        $this->mesa2->delete();
        $ana = $this->jogador('Ana');
        $beto = $this->jogador('Beto');
        $this->entrar($ana, TipoItem::Buyin);
        $this->entrar($beto, TipoItem::Buyin);
        $this->eliminar($beto);
        $caio = $this->chegou('Caio');

        $this->sortear([$caio]);

        $this->assertSame([$this->mesa->id, 2], [$caio->fresh()->jogo_mesa_id, $caio->fresh()->lugar]);
        $this->assertNull($beto->fresh()->jogo_mesa_id); // só guardava o lugar
        $this->assertSame(1, $ana->fresh()->lugar);
    }

    public function test_regras(): void
    {
        $ana = $this->jogador('Ana');
        $this->entrar($ana, TipoItem::Buyin);
        $this->entrar($this->jogador('Zeca'), TipoItem::Buyin);
        $this->eliminar($ana);

        $this->assertRegra('Ana caiu: faça o rebuy (VOLTAR no painel do jogador) antes de sortear o lugar.', fn () => $this->sortear([$ana]));
        $this->assertRegra('Escolha quem vai sentar.', fn () => $this->sortear([]));
        $this->assertRegra('Escolha ao menos uma mesa.', fn () => $this->sortear([$this->chegou('Beto')], []));

        // Mesa 02 com 1 lugar: 2 não cabem nas mesas escolhidas.
        $this->mesa2->update(['lugares' => 1]);
        $this->assertRegra(
            'Falta 1 lugar nas mesas escolhidas: marque mais mesas ou aumente os lugares.',
            fn () => $this->sortear([$this->chegou('Caio'), $this->chegou('Dani')], [$this->mesa2->id]),
        );
    }

    public function test_com_as_entradas_fechadas_ninguem_novo_senta_e_nada_fica_gravado(): void
    {
        $ana = $this->jogador('Ana');
        $this->entrar($ana, TipoItem::Buyin);
        $this->entrar($this->jogador('Zeca'), TipoItem::Buyin);
        app(AbrirIntervalo::class)($this->jogo, $this->admin);
        app(FecharEntradas::class)($this->jogo, $this->admin);
        $beto = $this->chegou('Beto');

        $this->assertRegra('As entradas já foram fechadas: Beto não entra mais.', fn () => $this->sortear([$ana, $beto], [$this->mesa2->id]));

        // Tudo ou nada: a Ana continua no lugar dela.
        $this->assertSame($this->mesa->id, $ana->fresh()->jogo_mesa_id);

        // Quebra de mesa: quem está jogando muda.
        $this->sortear([$ana], [$this->mesa2->id]);
        $this->assertSame($this->mesa2->id, $ana->fresh()->jogo_mesa_id);
    }

    public function test_operador_nao_sorteia(): void
    {
        $this->expectExceptionMessage('Você não tem permissão para sortear os lugares.');

        app(SortearLugares::class)($this->jogo, $this->operador, [$this->chegou('Ana')->id]);
    }

    public function test_modal_jogadores_marca_quem_chegou_cadastra_e_sorteia(): void
    {
        $this->withoutVite();
        $this->actingAs($this->admin, 'web');
        $this->jogador('Ana');
        $beto = $this->chegou('Beto');
        $this->chegou('Caio');
        Usuario::factory()->create(['nome' => 'Dudu do Cadastro', 'apelido' => null]);

        $modal = Livewire::test(Sorteio::class, ['jogo' => $this->jogo])
            ->dispatch('abrir-sorteio')
            ->assertSee(['Jogadores', 'Beto', 'Caio', 'Mesa 01', '8 lugares livres', 'Mesa 02', '9 lugares livres', 'Quem chegou já entra com o buy-in'])
            ->assertDontSee('Ana') // está na mesa: aparece na aba Na mesa
            ->call('alternar', $beto->id)
            ->assertSee('1 marcado · 17 lugares livres')
            // Do cadastro, pela busca, e um jogador novo: entram na lista já marcados.
            ->set('busca', 'Dudu')
            ->assertSee('No cadastro, fora da lista')
            ->call('adicionarDoCadastro', Usuario::query()->where('nome', 'Dudu do Cadastro')->value('id'))
            ->assertSet('aviso', 'Dudu do Cadastro entrou na lista')
            ->set('novoNome', 'Edu Novo')
            ->call('cadastrar')
            ->assertSee('3 marcados')
            // Só na Mesa 02.
            ->call('alternarMesa', $this->mesa->id)
            ->call('sortear')
            ->assertSet('erro', null)
            ->assertDispatched('caixa-mudou')
            ->assertSee(['Lugares sorteados', 'Mesa 02 · Lugar', 'Entregar 20.000 fichas']);

        $this->assertSame(3, $this->mesa2->participantes()->count());
        $this->assertCount(3, $modal->get('resultado'));

        $modal->call('verAba', 'sentados')->call('sortearMais')->assertSee('Ana');
    }

    public function test_tela_ao_vivo_tem_o_botao_e_nao_a_lista_de_baixo(): void
    {
        $this->withoutVite();
        $this->chegou('Beto');
        $this->actingAs($this->admin, 'web');

        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->assertSee(['JOGADORES', '1 sem lugar'])
            ->assertDontSee('rh-aovivo__espera', false);

        // O Operador não monta mesas: sem o botão.
        $this->actingAs($this->operador, 'web');
        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])->assertDontSee('JOGADORES');
    }
}
