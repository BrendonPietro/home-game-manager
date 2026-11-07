{{-- Painel de controle: App\Filament\Widgets\Atalhos. Estilos rh-atalhos no tema do painel. --}}
<x-filament-widgets::widget class="rh-wi-cheio">
    <x-filament::section heading="Atalhos">
        <div class="rh-atalhos">
            @foreach ($modulos as $modulo)
                <nav class="rh-atalhos__grupo" aria-label="{{ $modulo['grupo'] }}" wire:key="modulo-{{ md5($modulo['grupo']) }}">
                    <span class="rh-label">{{ $modulo['grupo'] }}</span>
                    @foreach ($modulo['itens'] as $item)
                        <a class="rh-atalho" href="{{ $item['url'] }}">
                            @if ($item['icone'])
                                <x-filament::icon :icon="$item['icone']" class="rh-atalho__icone" />
                            @endif
                            <span class="rh-atalho__rotulo">{{ $item['rotulo'] }}</span>
                            @if ($item['contexto'])
                                <span class="rh-atalho__ctx">{{ $item['contexto'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
