<?php

namespace Tests\Feature\Site;

use App\Livewire\Jogo\AoVivo;
use App\Livewire\Jogo\Sorteio;
use App\Models\Seguranca;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MontaNoite;
use Tests\TestCase;

/**
 * Nome com apelido nas listas de escolher jogador, o menu da topbar (Jogos, Acessar o painel, Sair)
 * e o recorte do dealer no desenho da mesa.
 */
class MenuEMesaTest extends TestCase
{
    use MontaNoite, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->montarNoite();
    }

    public function test_nome_com_apelido(): void
    {
        $nome = fn (string $nome, ?string $apelido) => (new Usuario(['nome' => $nome, 'apelido' => $apelido]))->nome_com_apelido;

        $this->assertSame('Ricardo Alves (RICO)', $nome('Ricardo Alves', 'RICO'));
        $this->assertSame('Pedro Lima (Pedrinho)', $nome('Pedro Lima', 'Pedrinho '));
        $this->assertSame('TUCA', $nome('TUCA', 'tuca'));
        $this->assertSame('Bruna', $nome('Bruna', null));
    }

    public function test_quem_senta_aqui_e_o_modal_jogadores_mostram_nome_e_apelido(): void
    {
        $this->actingAs($this->admin, 'web');
        $this->jogo->participantes()->create(['usuario_id' => Usuario::factory()->create(['nome' => 'Ricardo Alves', 'apelido' => 'RICO'])->id]);

        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->call('clicarAssento', $this->mesa->id, 3)
            ->assertSee('Ricardo Alves (RICO)');

        Livewire::test(Sorteio::class, ['jogo' => $this->jogo])
            ->dispatch('abrir-sorteio')
            ->assertSee('Ricardo Alves (RICO)');
    }

    public function test_menu_com_jogos_painel_e_sair(): void
    {
        $this->admin->update(['login' => 'fulanodetal']);
        $this->actingAs($this->admin, 'web');

        // Sem conta no painel: Jogos e Sair.
        $this->get(route('jogos.index'))
            ->assertOk()
            ->assertSee(['aria-label="Menu"', 'role="menuitem" href="'.route('jogos.index').'"'], false)
            ->assertSee('Sair')
            ->assertDontSee('Acessar o painel');

        // Conta do clube (o mesmo usuário nos dois lados): o painel abre em outra aba.
        Seguranca::factory()->create(['login' => 'fulanodetal']);

        $this->get(route('jogos.index'))
            ->assertSee('Acessar o painel')
            ->assertSee('href="'.url('/painel').'" target="_blank" rel="noopener"', false);
    }

    public function test_mesa_desenha_o_lugar_do_dealer(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::test(AoVivo::class, ['jogo' => $this->jogo])
            ->assertSeeHtml('data-dealer="baixo"');
    }
}
