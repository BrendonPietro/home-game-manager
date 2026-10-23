<?php

namespace App\View\Components\Site;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Esqueleto HTML do site de operação (head, Vite, Livewire). <x-site.app titulo="...">
 */
class App extends Component
{
    public function __construct(
        public string $titulo = '',
    ) {}

    public function render(): View|Closure|string
    {
        return view('site.layouts.app');
    }
}
