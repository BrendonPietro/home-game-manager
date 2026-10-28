{{-- Gráfico de barras do dashboard (App\Filament\Widgets\GraficoDeBarras). --}}
<x-filament-widgets::widget>
    <x-filament::section :heading="$titulo" :description="$descricao">
        @if ($barras === [])
            <p class="rh-grafico-vazio">Ainda não há jogo finalizado.</p>
        @else
            <div class="rh-bars" role="img" aria-label="{{ $titulo }}: {{ collect($barras)->map(fn ($b) => $b['dica'].' '.$b['completo'])->join(', ') }}">
                @foreach ($barras as $barra)
                    <div @class(['rh-bars__c', 'is-hl' => $loop->last])>
                        <span class="rh-tip">{{ $barra['dica'] }}: {{ $barra['completo'] }}</span>
                        <span class="rh-bars__v">{{ $barra['texto'] }}</span>
                        <div class="rh-bars__b" style="height: {{ $barra['altura'] }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="rh-bars__x">
                @foreach ($barras as $barra)
                    <span>{{ $barra['rotulo'] }}</span>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
