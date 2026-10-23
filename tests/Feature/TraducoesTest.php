<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Tudo que o Filament tem em inglês precisa existir em pt_BR (no pacote ou em lang/vendor/).
 * Se falhar depois de atualizar o Filament, traduza as chaves listadas em lang/vendor/<namespace>/pt_BR/.
 */
class TraducoesTest extends TestCase
{
    public function test_filament_inteiro_em_portugues(): void
    {
        $faltando = [];

        foreach (glob(base_path('vendor/filament/*/resources/lang/en/*.php')) as $arquivo) {
            $pacote = basename(dirname($arquivo, 4));
            $namespace = $pacote === 'filament' ? 'filament-panels' : 'filament-'.$pacote;
            $grupo = basename($arquivo, '.php');

            foreach (array_keys(Arr::dot(require $arquivo)) as $chave) {
                if (! Lang::has("{$namespace}::{$grupo}.{$chave}", 'pt_BR', false)) {
                    $faltando[] = "{$namespace}::{$grupo}.{$chave}";
                }
            }
        }

        $this->assertSame([], $faltando, 'Textos do Filament sem tradução pt_BR.');
    }

    public function test_validacao_em_portugues(): void
    {
        $this->assertSame('O campo e-mail é obrigatório.', __('validation.required', ['attribute' => 'e-mail'], 'pt_BR'));
        $this->assertSame('Selecione uma opção.', __('filament-forms::components.select.required_description', [], 'pt_BR'));
        $this->assertSame('Selecione uma opção', __('filament-forms::components.select.placeholder', [], 'pt_BR'));
    }
}
