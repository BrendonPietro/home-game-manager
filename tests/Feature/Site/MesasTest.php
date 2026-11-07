<?php

namespace Tests\Feature\Site;

use App\Enums\StatusJogo;
use App\Livewire\Jogo\Mesas;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Participante;
use App\Models\Usuario;
use Database\Seeders\PermissoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MesasTest extends TestCase
{
    use RefreshDatabase;

    private Jogo $jogo;

    private JogoMesa $mesa1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(PermissoesSeeder::class);
        $this->entrarComo('Admin');

        $this->jogo = Jogo::factory()->agendado()->comEntradasPadrao()->create(['nome' => 'Poker Night #19']);
        $this->mesa1 = $this->jogo->mesas()->create(['nome' => 'Mesa 01', 'lugares' => 9, 'ordem' => 1]);
    }

    /** Com site.fora_da_noite: as mesas se montam antes do jogo começar. */
    private function entrarComo(string $grupo): void
    {
        $usuario = Usuario::factory()->foraDaNoite()->create();
        $usuario->assignRole($grupo);
        $this->actingAs($usuario, 'web');
    }

    private function jogador(string $nome, ?int $lugar = null): Participante
    {
        return $this->jogo->participantes()->create([
            'usuario_id' => Usuario::factory()->create(['nome' => $nome, 'apelido' => null])->id,
            'jogo_mesa_id' => $lugar ? $this->mesa1->id : null,
            'lugar' => $lugar,
        ]);
    }

    public function test_pagina_mostra_mesa_e_quem_esta_sem_lugar(): void
    {
        $this->jogador('João', 1);
        $this->jogador('Zeca');

        $this->get(route('jogos.mesas', $this->jogo))
            ->assertOk()
            ->assertSee('Antes do torneio · 3 de 3')
            ->assertSee('Mesa 01')
            ->assertSee('1/9')
            ->assertSee('Lugar 1: João')
            ->assertSee('2 · vago')
            ->assertSeeInOrder(['Sem lugar', 'Zeca'])
            ->assertSee('Sortear lugares')
            ->assertSee('Iniciar torneio')
            ->assertSee('--x0:', false); // posições de partida vindas do servidor
    }

    public function test_selecionar_e_mover_trocar_e_tirar(): void
    {
        $joao = $this->jogador('João', 1);
        $zeca = $this->jogador('Zeca');

        $tela = Livewire::test(Mesas::class, ['jogo' => $this->jogo])
            ->call('selecionar', $zeca->id)
            ->assertSet('selecionado', $zeca->id)
            ->assertSee('selecionado: clique num lugar vazio')
            ->call('clicarAssento', $this->mesa1->id, 4)
            ->assertSet('selecionado', null);
        $this->assertSame(4, $zeca->fresh()->lugar);

        // Clicar num ocupado seleciona; clicar noutro ocupado troca os dois.
        $tela->call('clicarAssento', $this->mesa1->id, 1)
            ->assertSet('selecionado', $joao->id)
            ->call('clicarAssento', $this->mesa1->id, 4);
        $this->assertSame([4, 1], [$joao->fresh()->lugar, $zeca->fresh()->lugar]);

        // Clicar no selecionado de novo desmarca; "Tirar do lugar" leva para sem lugar.
        $tela->call('clicarAssento', $this->mesa1->id, 1)
            ->call('clicarAssento', $this->mesa1->id, 1)
            ->assertSet('selecionado', null)
            ->call('clicarAssento', $this->mesa1->id, 1)
            ->assertSee('Tirar Zeca do lugar')
            ->call('semLugar');
        $this->assertNull($zeca->fresh()->lugar);
    }

    public function test_lugar_vazio_escolhe_quem_senta(): void
    {
        $ana = $this->jogador('Ana');
        $this->jogador('Beto', 2);
        $carla = Usuario::factory()->create(['nome' => 'Carla Fora', 'apelido' => null]);

        // Confirmado sem lugar: quem já sentou (Beto) não aparece.
        $tela = Livewire::test(Mesas::class, ['jogo' => $this->jogo])
            ->call('clicarAssento', $this->mesa1->id, 1)
            ->assertSet('lugarVazio', [$this->mesa1->id, 1])
            ->assertSee(['Mesa 01 · Lugar 1', 'Confirmados sem lugar · 1', 'Ana'])
            ->assertSee('O buy-in (Buy-in · R$ 100) entra quando o torneio começar')
            ->call('sentarNoLugar', $ana->id)
            ->assertSet('lugarVazio', null);

        $this->assertSame([$this->mesa1->id, 1], [$ana->fresh()->jogo_mesa_id, $ana->fresh()->lugar]);

        // Alguém do cadastro que não estava na lista: confirma e senta.
        $tela->call('clicarAssento', $this->mesa1->id, 3)
            ->assertSee('Todos os confirmados já têm lugar')
            ->set('busca', 'carla')
            ->assertSee(['No cadastro, fora da lista', 'Carla Fora'])
            ->call('sentarDoCadastro', $carla->id)
            ->assertSet('lugarVazio', null);

        $this->assertSame(3, $this->jogo->participantes()->where('usuario_id', $carla->id)->sole()->lugar);

        // Jogador novo: cadastra, confirma e senta; sem nome, avisa no modal.
        $tela->call('clicarAssento', $this->mesa1->id, 4)
            ->call('cadastrarNoLugar')
            ->assertSee('Informe o nome do jogador.')
            ->set('novoNome', 'Dudu Novo')
            ->call('cadastrarNoLugar')
            ->assertSet('lugarVazio', null);

        $this->assertSame(4, Usuario::query()->where('nome', 'Dudu Novo')->sole()->participacoes()->sole()->lugar);
    }

    public function test_sortear_e_ajustar_pela_tela(): void
    {
        foreach (['João', 'Lucas', 'Carlos'] as $nome) {
            $this->jogador($nome);
        }

        Livewire::test(Mesas::class, ['jogo' => $this->jogo])
            ->call('sortear')
            ->assertSee('3/9')
            ->call('ajustarLugares', $this->mesa1->id, '2')
            ->assertSee('Tem jogador no lugar');
    }

    public function test_adicionar_mesa_sem_o_cadastro_de_mesas(): void
    {
        $this->jogo->forceFill(['lugares_por_mesa' => 8])->save();

        Livewire::test(Mesas::class, ['jogo' => $this->jogo])
            ->assertSee('Adicionar mesa')
            ->call('adicionarMesa')
            ->assertHasNoErrors()
            ->assertSee('Mesa 2');

        $this->assertSame([['Mesa 01', 9], ['Mesa 2', 8]], $this->jogo->mesas()->get()->map(fn ($m) => [$m->nome, $m->lugares])->all());
    }

    public function test_iniciar_leva_para_a_tela_ao_vivo(): void
    {
        $this->jogador('João', 1);
        $this->jogador('Lucas', 2);

        Livewire::test(Mesas::class, ['jogo' => $this->jogo])
            ->call('iniciar')
            ->assertRedirect(route('jogos.ao-vivo', $this->jogo));

        $this->assertSame(StatusJogo::EmAndamento, $this->jogo->fresh()->status);
        $this->assertSame('Poker Night #19 começou.', session('aviso'));
    }

    public function test_erro_de_regra_aparece_na_tela(): void
    {
        Livewire::test(Mesas::class, ['jogo' => $this->jogo])
            ->call('iniciar')
            ->assertNoRedirect()
            ->assertSee('Sente pelo menos 2 jogadores antes de iniciar.');
    }

    public function test_operador_so_ve(): void
    {
        $this->entrarComo('Operador');
        $this->jogador('João', 1);

        $this->get(route('jogos.mesas', $this->jogo))
            ->assertOk()
            ->assertSee('Lugar 1: João')
            ->assertDontSee('Sortear lugares')
            ->assertDontSee('Iniciar torneio')
            ->assertDontSee('clicarAssento');
    }

    public function test_jogadores_leva_para_as_mesas(): void
    {
        $this->get(route('jogos.jogadores', $this->jogo))
            ->assertSee('href="'.route('jogos.mesas', $this->jogo).'"', false);
    }
}
