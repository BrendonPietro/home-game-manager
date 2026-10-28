{{-- Tela 25: configurações (App\Filament\Pages\Configuracoes). --}}
<x-filament-panels::page>
    <form wire:submit="salvar" class="rh-configuracoes">
        {{ $this->form }}

        <div class="rh-configuracoes__acoes">
            <x-filament::button type="submit">Salvar alterações</x-filament::button>
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\Configuracoes::getUrl()">Descartar</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
