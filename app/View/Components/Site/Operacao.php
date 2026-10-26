<?php

namespace App\View\Components\Site;

use App\Models\Jogo;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Layout das telas da noite (siteShell do protótipo): topbar com o jogo, fluxo da noite e o conteúdo.
 * <x-site.operacao :jogo="$jogo" etapa="conferencia" titulo="Conferência">…</x-site.operacao>
 *
 * `larga`: conteúdo de ponta a ponta, sem margem nem largura máxima (tela ao vivo, que traz a
 * própria faixa ao vivo dentro do Livewire para atualizar junto).
 */
class Operacao extends Component
{
    public function __construct(
        public Jogo $jogo,
        public string $etapa,
        public string $titulo = '',
        public bool $larga = false,
    ) {}

    public function render(): View|Closure|string
    {
        return view('site.layouts.operacao');
    }
}
