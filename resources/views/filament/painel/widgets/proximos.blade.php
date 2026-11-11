{{-- Próximos eventos (App\Filament\Widgets\Proximos), ao lado dos atalhos no Painel de controle. --}}
@php
    use App\Support\Datas;

    $pontos = ['jogo' => 'rh-dot--tour', 'aluguel' => 'rh-dot--rent', 'reserva' => 'rh-dot--res', 'bloqueio' => 'rh-dot--block'];
@endphp

<x-filament-widgets::widget class="rh-wi-proximos rh-wi-cheio">
    <x-filament::section heading="Próximos">
        <x-slot name="afterHeader">
            <x-filament::link :href="$calendario" icon="rh-chevron-direita" icon-position="after" size="sm">Calendário</x-filament::link>
        </x-slot>

        @if ($eventos->isEmpty())
            <p class="rh-grafico-vazio">Nada na agenda daqui para a frente.</p>
        @else
            <div class="rh-list">
                @foreach ($eventos as $evento)
                    <a class="rh-li" {{ \Filament\Support\generate_href_html($this->urlDoMes($evento)) }} wire:key="proximo-{{ $evento->id }}">
                        <span class="rh-datebox rh-datebox--sm">
                            <span>{{ Datas::mes($evento->data) }}</span>
                            <b>{{ $evento->data->format('d') }}</b>
                        </span>
                        <span class="rh-li__txt">
                            <span class="rh-li__t">{{ $evento->nome }}</span>
                            <span class="rh-li__s">{{ \App\Filament\Widgets\Proximos::detalhe($evento) }}</span>
                        </span>
                        <span class="rh-dot {{ $pontos[$evento->tipo->value] }}"></span>
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
