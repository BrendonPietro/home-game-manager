<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Favicon e ícones do logo (php artisan icones:gerar): os arquivos existem e o site e o painel apontam para eles. */
class IconesTest extends TestCase
{
    use RefreshDatabase;

    public function test_arquivos_gerados(): void
    {
        // .ico de verdade (cabeçalho 00 00 01 00) com os três tamanhos de aba.
        $ico = file_get_contents(public_path('favicon.ico'));
        $this->assertSame("\x00\x00\x01\x00", substr($ico, 0, 4));
        $this->assertSame(3, unpack('v', substr($ico, 4, 2))[1]);

        $this->assertSame([192, 192], array_slice(getimagesize(public_path('icone-192.png')), 0, 2));
        $this->assertSame([180, 180], array_slice(getimagesize(public_path('apple-touch-icon.png')), 0, 2));
    }

    public function test_site_e_painel_apontam_para_os_icones(): void
    {
        foreach (['/login', '/painel/login'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('favicon.ico?v=', false)
                ->assertSee('icone-192.png?v=', false)
                ->assertSee('rel="apple-touch-icon"', false)
                // As fontes mais usadas já vêm pré-carregadas (o texto não pisca na troca de página).
                ->assertSee(['Barlow-400', 'Barlow-500', 'BarlowCondensed-500'], false)
                ->assertSee('as="font" type="font/woff2" crossorigin', false);
        }
    }
}
