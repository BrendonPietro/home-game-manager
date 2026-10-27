<?php

namespace Tests\Feature\Site;

use App\Actions\Jogo\FecharEntradas;
use App\Actions\Jogo\RegistrarCashOut;
use App\Actions\Jogo\RegistrarEliminacao;
use App\Enums\TipoItem;
use App\Livewire\Jogo\AcoesJogador;
use App\Livewire\Jogo\Resultado;
use App\Models\Jogo;
use App\Models\Participante;
use Database\Seeders\PokerNight18Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

class ResultadoTest extends TestCase
{
    use MontaNoite;
    use RefreshDatabase;

    private Jogo $noite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // A noite do MontaNoite é um cash game; a #18 é o torneio de referência.
        $this->montarNoite(Jogo::factory()->cashGame()->emAndamento()->comEntradasPadrao()->create());
        $this->seed(PokerNight18Seeder::class);
        $this->noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
        $this->actingAs($this->admin, 'web');
    }

    private function da18(string $nome): Participante
    {
        return $this->noite->participantes()->whereHas('usuario', fn ($q) => $q->where('nome', $nome))->sole();
    }

    /** Fecha as entradas da #18 e elimina todos menos Marcão, Felipe e Diego. */
    private function sobramTres(): void
    {
        app(FecharEntradas::class)($this->noite, $this->admin);

        foreach (['João', 'Lucas', 'Carlos', 'Rafael', 'André', 'Bruno', 'Pedro', 'Brendon', 'Thiago'] as $nome) {
            app(RegistrarEliminacao::class)($this->da18($nome), $this->admin);
        }
    }

    public function test_so_abre_depois_de_fechar_as_entradas(): void
    {
        $this->get(route('jogos.resultado', $this->noite))
            ->assertRedirect(route('jogos.ao-vivo', $this->noite))
            ->assertSessionHas('aviso', 'Resultado abre depois de fechar as entradas.');
    }

    public function test_definir_quem_ainda_esta_em_jogo(): void
    {
        $this->sobramTres();
        [$marcos, $felipe, $diego] = [$this->da18('Marcos'), $this->da18('Felipe'), $this->da18('Diego')];

        $this->get(route('jogos.premiacao', $this->noite))->assertSee('REGISTRAR RESULTADO');

        Livewire::test(Resultado::class, ['jogo' => $this->noite])
            ->assertSeeInOrder(['1º lugar · 50%', 'a definir', 'R$ 945', '2º lugar · 30%', 'a definir', 'R$ 567'])
            ->assertSee('Escolha a de quem ainda está em jogo (do 1º ao 3º).')
            // Quem caiu já tem a posição: Thiago foi o último a cair, 4º.
            ->assertSeeInOrder(['4º', 'Thiago', '5º', 'Brendon'])
            // Escolher a mesma posição de outro tira dele.
            ->set("posicoes.{$marcos->id}", '1')
            ->set("posicoes.{$felipe->id}", '1')
            ->assertSet("posicoes.{$marcos->id}", '')
            ->set("posicoes.{$marcos->id}", '2')
            ->set("posicoes.{$diego->id}", '3')
            ->call('salvar')
            ->assertSet('aviso', 'Resultado gravado.')
            ->assertSee(['Felipe', 'Marcão', 'Diego', '+R$ 845', '+R$ 317', '+R$ 278'])
            ->assertDontSee('a definir')
            ->assertDontSeeHtml('wire:model.live="posicoes');

        $this->assertSame([1, 2, 3], [$felipe->fresh()->posicao_final, $marcos->fresh()->posicao_final, $diego->fresh()->posicao_final]);
    }

    public function test_array_inteiro_vindo_do_navegador(): void
    {
        $this->sobramTres();

        // O navegador manda o array todo quando ele ainda está vazio.
        Livewire::test(Resultado::class, ['jogo' => $this->noite])
            ->set('posicoes', [$this->da18('Marcos')->id => '1'])
            ->set("posicoes.{$this->da18('Felipe')->id}", '2')
            ->set("posicoes.{$this->da18('Diego')->id}", '3')
            ->assertSee('3 de 3 posições escolhidas.')
            ->call('salvar')
            ->assertSet('aviso', 'Resultado gravado.');

        $this->assertSame(1, $this->da18('Marcos')->posicao_final);
    }

    public function test_operador_ve_mas_nao_define(): void
    {
        $this->sobramTres();
        $this->actingAs($this->operador, 'web');

        Livewire::test(Resultado::class, ['jogo' => $this->noite])
            ->assertSee('em jogo')
            ->assertDontSeeHtml('wire:model.live="posicoes')
            ->call('salvar')
            ->assertSet('erro', 'Só o Admin do site define o resultado.');
    }

    public function test_cash_game_ordena_pelo_lucro_e_abre_o_painel(): void
    {
        [$ana, $beto, $caio] = array_map(function (string $nome) {
            $jogador = $this->jogador($nome);
            $this->entrar($jogador, TipoItem::Buyin);

            return $jogador;
        }, ['Ana', 'Beto', 'Caio']);

        // Ficha de R$ 0,01: Ana sai com R$ 40, Beto com R$ 230; Caio ainda está na mesa.
        app(RegistrarCashOut::class)($ana, 4000, $this->operador);
        app(RegistrarCashOut::class)($beto, 23000, $this->operador);

        Livewire::test(Resultado::class, ['jogo' => $this->jogo])
            ->assertSee('Cash-outs')
            ->assertDontSee('lugar ·')
            ->assertSeeInOrder(['1º', 'Beto', 'R$ 230', '+R$ 130', '2º', 'Ana', 'R$ 40', 'na mesa', 'Caio', 'pendente'])
            ->call('selecionar', $caio->id)
            ->assertSeeLivewire(AcoesJogador::class);
    }
}
