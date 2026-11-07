<?php

namespace Tests\Feature\Site;

use App\Livewire\Jogo\Jogadores;
use App\Models\Jogo;
use App\Models\Mesa;
use App\Models\Usuario;
use Database\Seeders\PermissoesSeeder;
use Database\Seeders\PokerNight18Seeder;
use Database\Seeders\RegrasPremiacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JogadoresTest extends TestCase
{
    use RefreshDatabase;

    private Jogo $jogo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissoesSeeder::class);

        // Operador com a permissão avulsa site.fora_da_noite: sem ela, o site só abre com jogo em andamento.
        $operador = Usuario::factory()->foraDaNoite()->create(['nome' => 'Operador', 'apelido' => null]);
        $operador->assignRole('Operador');
        $this->actingAs($operador, 'web');

        $this->jogo = Jogo::factory()->agendado()->comEntradasPadrao()->create(['nome' => 'Poker Night #19']);
        foreach (['Mesa 01', 'Mesa 02'] as $ordem => $nome) {
            $this->jogo->mesas()->create(['mesa_id' => Mesa::factory()->create(['nome' => $nome])->id, 'lugares' => 9, 'ordem' => $ordem]);
        }
    }

    private function jogador(string $nome, ?string $apelido = null): Usuario
    {
        return Usuario::factory()->create(['nome' => $nome, 'apelido' => $apelido]);
    }

    public function test_pagina_abre_no_layout_da_noite(): void
    {
        $this->jogador('João');

        $this->get(route('jogos.jogadores', $this->jogo))
            ->assertOk()
            ->assertSee('Quem vai jogar hoje?')
            ->assertSee('Antes do torneio · 2 de 3')
            ->assertSee('João')
            ->assertSee('NOVO JOGADOR')
            ->assertSeeText('2 mesas · 18 lugares · sobram 18')
            ->assertSeeLivewire(Jogadores::class);
    }

    public function test_clicar_confirma_e_clicar_de_novo_tira(): void
    {
        $joao = $this->jogador('João');

        $tela = Livewire::test(Jogadores::class, ['jogo' => $this->jogo])
            ->call('alternar', $joao->id)
            ->assertSee('sobram 17')
            ->assertSet('erro', null);

        $this->assertSame([$joao->id], $this->jogo->participantes()->pluck('usuario_id')->all());

        $tela->call('alternar', $joao->id)->assertSee('Ninguém confirmado ainda');
        $this->assertSame(0, $this->jogo->participantes()->count());

        $tela->call('alternar', $joao->id)->call('remover', $joao->id);
        $this->assertSame(0, $this->jogo->participantes()->count());
    }

    public function test_busca_ignora_acento_e_maiuscula(): void
    {
        $this->jogador('João');
        $this->jogador('Lucas');
        $this->jogador('Marcos', 'Marcão');

        Livewire::test(Jogadores::class, ['jogo' => $this->jogo])
            ->set('busca', 'JOAO')
            ->assertSee('João')
            ->assertDontSee('Lucas')
            ->set('busca', 'marcos')
            ->assertSee('Marcão'); // acha pelo nome e mostra o apelido
    }

    public function test_filtro_selecionados(): void
    {
        $joao = $this->jogador('João');
        $this->jogador('Lucas');

        Livewire::test(Jogadores::class, ['jogo' => $this->jogo])
            ->call('alternar', $joao->id)
            ->set('filtro', 'selecionados')
            ->assertSee('Selecionados (1)')
            ->assertDontSee('Lucas');
    }

    public function test_cadastro_rapido_pelo_modal(): void
    {
        $this->jogador('Marcos', 'Marcão');

        Livewire::test(Jogadores::class, ['jogo' => $this->jogo])
            ->call('abrirNovo')
            ->assertSee('Novo jogador')
            ->call('cadastrar')
            ->assertHasErrors(['novoNome' => 'required'])
            ->set('novoNome', 'Marcão')
            ->call('cadastrar')
            ->assertSet('novo', true)
            ->assertSee('Já existe Marcão no cadastro: confirme pela lista.')
            ->set('novoNome', 'Zeca')
            ->call('cadastrar')
            ->assertSet('novo', false)
            ->assertSee('Zeca');

        $this->assertSame(['Zeca'], $this->jogo->participantes()->with('usuario')->get()->pluck('usuario.nome')->all());
    }

    public function test_quem_tem_lancamento_mostra_o_aviso(): void
    {
        $this->seed([RegrasPremiacaoSeeder::class, PokerNight18Seeder::class]);
        $noite = Jogo::query()->where('nome', PokerNight18Seeder::NOME)->sole();
        $joao = Usuario::query()->where('nome', 'João')->sole();

        Livewire::test(Jogadores::class, ['jogo' => $noite])
            ->call('alternar', $joao->id)
            ->assertSee('João já tem lançamento nesta noite: não sai da lista.');
    }

    public function test_conferencia_segue_para_os_jogadores(): void
    {
        $this->get(route('jogos.conferencia', $this->jogo))
            ->assertSee('href="'.route('jogos.jogadores', $this->jogo).'"', false);
    }
}
