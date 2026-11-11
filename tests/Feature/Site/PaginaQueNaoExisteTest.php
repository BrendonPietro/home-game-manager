<?php

namespace Tests\Feature\Site;

use App\Models\Usuario;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UsaPainel;
use Tests\TestCase;

/**
 * Página que não existe (jogo excluído, link antigo, digitado errado) volta para o início com um aviso,
 * no site e no painel, em vez do 404. Arquivo, JSON e pedido que não é GET continuam no 404.
 */
class PaginaQueNaoExisteTest extends TestCase
{
    use RefreshDatabase, UsaPainel;

    private function entrarNoSite(): Usuario
    {
        $this->entrarNoPainel('Desenvolvedor'); // semeia as permissões dos dois lados
        $admin = Usuario::factory()->comAcesso()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin, 'web');

        return $admin;
    }

    public function test_jogo_excluido_volta_para_a_home_com_o_aviso(): void
    {
        $this->entrarNoSite();

        $this->get('/jogos/999/ao-vivo')
            ->assertRedirect(route('home'))
            ->assertSessionHas('aviso', 'Esse jogo não existe mais.');

        $this->followingRedirects()->get('/jogos/999/resumo')->assertOk()->assertSee('Esse jogo não existe mais.');

        $this->get('/nao-existe')
            ->assertRedirect(route('home'))
            ->assertSessionHas('aviso', 'Essa página não existe.');
    }

    public function test_sem_login_vai_para_o_login(): void
    {
        $this->get('/jogos/999/ao-vivo')->assertRedirect(route('login'));
    }

    public function test_arquivo_json_e_pedido_que_nao_e_get_continuam_no_404(): void
    {
        $this->entrarNoSite();

        $this->get('/build/assets/nao-existe.js')->assertNotFound();
        $this->getJson('/jogos/999/ao-vivo')->assertNotFound();
        $this->post('/nao-existe')->assertNotFound();
    }

    public function test_painel_volta_para_o_painel_de_controle(): void
    {
        $this->entrarNoPainel('Desenvolvedor');

        $this->get('/painel/jogos/999/edit')
            ->assertRedirect(Filament::getPanel('painel')->getUrl());
        Notification::assertNotified('Esse jogo não existe mais.');

        $this->get('/painel/nao-existe')->assertRedirect(Filament::getPanel('painel')->getUrl());
    }
}
