<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Support\IconesDoPainel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
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

    /** Ícone que falta derruba a página (o blade-icons não acha o SVG): todo nome usado tem arquivo em resources/svg/icones. */
    public function test_todo_icone_usado_tem_arquivo_na_pasta(): void
    {
        $existem = array_map(fn (string $f): string => 'rh-'.basename($f, '.svg'), glob(resource_path('svg/icones/*.svg')));
        $usados = array_values(IconesDoPainel::apelidos());

        $arquivos = fn (string $pasta, string $fim) => collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pasta)))
            ->filter(fn (SplFileInfo $f): bool => str_ends_with($f->getFilename(), $fim));

        foreach ($arquivos(resource_path('views'), '.blade.php') as $arquivo) {
            $texto = file_get_contents($arquivo);
            // Só o 1º argumento do @svg é o ícone (o 2º são classes); @@svg é texto de exemplo.
            preg_match_all('/(?<!@)@svg\(\s*((?:[^,()]|\([^()]*\))*)|\bicon="rh-[a-z0-9-]+"/', $texto, $chamadas);
            foreach ($chamadas[0] as $chamada) {
                preg_match_all('/(rh-[a-z0-9-]+)/', $chamada, $nomes);
                array_push($usados, ...$nomes[1]);
            }
        }

        foreach ($arquivos(app_path(), '.php') as $arquivo) {
            $texto = file_get_contents($arquivo);
            preg_match_all('/navigationIcon = \'(rh-[a-z0-9-]+)\'|->icon\((?:[^()]|\([^()]*\))*\)|\'(?:rh-[a-z0-9-]+)\'\],/', $texto, $chamadas);
            foreach ($chamadas[0] as $chamada) {
                preg_match_all('/\'(rh-[a-z0-9-]+)\'/', $chamada, $nomes);
                array_push($usados, ...$nomes[1]);
            }
        }

        $this->assertGreaterThan(50, count($usados));
        $this->assertSame([], array_values(array_diff(array_unique($usados), $existem)), 'Ícone usado sem arquivo em resources/svg/icones');

        // Cada item do menu do painel tem o próprio arquivo (menu-…), para trocar sem mexer nos outros.
        foreach (Filament::getPanel('painel')->getResources() + Filament::getPanel('painel')->getPages() as $classe) {
            if ($classe::getNavigationGroup() !== null || $classe === Dashboard::class) {
                $this->assertStringStartsWith('rh-menu-', (string) $classe::getNavigationIcon(), $classe);
            }
        }
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
