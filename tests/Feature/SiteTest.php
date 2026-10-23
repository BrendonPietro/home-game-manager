<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_layout_do_site(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="pt-BR">', false)
            ->assertSee('rh-login__card', false)
            ->assertSee('window.livewireScriptConfig', false)
            ->assertDontSee('fonts.googleapis.com')
            ->assertDontSee('fonts.bunny.net');
    }

    public function test_icones_do_design_system(): void
    {
        $html = svg('rh-home')->toHtml();

        $this->assertStringContainsString('class="rh-ic"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('viewBox="0 0 24 24"', $html);
    }

    public function test_diretiva_brl(): void
    {
        $this->assertSame(
            'R$ 1.890 · +R$ 795 · −R$ 200',
            Blade::render('@brl($a) · @brl($b, true) · @brl($c, true)', ['a' => '1890.00', 'b' => '795.00', 'c' => '-200.00']),
        );
    }

    public function test_vitrine_so_existe_em_ambiente_local(): void
    {
        $this->get('/vitrine')->assertNotFound();
    }
}
