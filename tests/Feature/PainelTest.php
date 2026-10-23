<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\Seguranca;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Painel /painel: guard `painel`, contas da tabela `seguranca`, login pela coluna `login`.
 */
class PainelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Filament::setCurrentPanel('painel');
    }

    public function test_visitante_vai_para_o_login_do_painel(): void
    {
        $this->get('/painel')->assertRedirect('/painel/login');

        $this->get('/painel/login')
            ->assertOk()
            ->assertSee('Painel administrativo')
            ->assertSee('Usuário')
            ->assertSee('Entrar no painel')
            ->assertSee('Entrar no site de operação');
    }

    public function test_fontes_sao_locais(): void
    {
        $this->get('/painel/login')
            ->assertSee("--font-family: 'Barlow'", false)
            ->assertDontSee('fonts.bunny.net')
            ->assertDontSee('fonts.googleapis.com');
    }

    public function test_conta_entra_pelo_login_e_registra_ultimo_acesso(): void
    {
        $conta = Seguranca::factory()->create(['login' => 'organizador']);

        Livewire::test(Login::class)
            ->fillForm(['login' => 'organizador', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(Filament::getUrl());

        $this->assertAuthenticatedAs($conta, 'painel');
        $this->assertGuest('web');
        $this->assertNotNull($conta->fresh()->ultimo_acesso);

        $this->get('/painel')->assertOk()->assertSee($conta->nome);
    }

    public function test_volta_para_a_pagina_pretendida_do_painel_mas_nao_do_site(): void
    {
        Seguranca::factory()->create(['login' => 'organizador']);

        session(['url.intended' => url('/painel/mesas')]);

        Livewire::test(Login::class)
            ->fillForm(['login' => 'organizador', 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect(url('/painel/mesas'));

        Filament::auth()->logout();

        // Quem tentou abrir o site antes não vai parar no login do site ao entrar no painel.
        session(['url.intended' => url('/')]);

        Livewire::test(Login::class)
            ->fillForm(['login' => 'organizador', 'password' => 'password'])
            ->call('authenticate')
            ->assertRedirect(Filament::getUrl());
    }

    public function test_senha_errada_nao_entra(): void
    {
        Seguranca::factory()->create(['login' => 'organizador']);

        Livewire::test(Login::class)
            ->fillForm(['login' => 'organizador', 'password' => 'errada'])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest('painel');
    }

    public function test_conta_desativada_nao_entra(): void
    {
        Seguranca::factory()->create(['login' => 'antigo', 'ativo' => false]);

        Livewire::test(Login::class)
            ->fillForm(['login' => 'antigo', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest('painel');
    }

    public function test_login_do_site_nao_abre_o_painel(): void
    {
        $operador = Usuario::factory()->comAcesso()->create();

        $this->actingAs($operador, 'web')
            ->get('/painel')
            ->assertRedirect('/painel/login');
    }
}
