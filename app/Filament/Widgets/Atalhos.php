<?php

namespace App\Filament\Widgets;

use App\Services\Painel\Atalhos as ModulosDoPainel;
use Filament\Widgets\Widget;

/** Painel de controle: os módulos do menu como atalhos, cada tela com uma linha de contexto (Services\Painel\Atalhos). */
class Atalhos extends Widget
{
    protected string $view = 'filament.painel.widgets.atalhos';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    protected static bool $isLazy = false;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['modulos' => app(ModulosDoPainel::class)->modulos()];
    }
}
