<?php

namespace Tests\Feature\Site;

use App\Enums\StatusJogo;
use App\Livewire\Jogo\Mesas;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Mesa;
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
        $this->mesa1 = $this->jogo->mesas()->create(['mesa_id' => Mesa::factory()->create(['nome' => 'Mesa 01'])->id, 'lugares' => 9, 'ordem' => 1]);
    }

    private function entrarComo(string $grupo): void
    {
        $usuario = Usuario::factory()->comAcesso()->create();
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
