{{-- Tela 17: grade do mês (App\Filament\Pages\Calendario). Estilos rh-cal / rh-ev / rh-dot no tema do painel. --}}
@php
    $classes = [
        'jogo' => 'rh-ev--tour',
        'aluguel' => 'rh-ev--rent',
        'reserva' => 'rh-ev--res',
        'bloqueio' => 'rh-ev--block',
    ];
    $podeCriar = $this->podeCriar();
@endphp

<x-filament-panels::page>
    <div class="rh-cal-hd">
        <div class="rh-cal-hd__nav">
            <x-filament::icon-button icon="rh-chev-l" color="gray" label="Mês anterior" wire:click="mudarMes(-1)" />
            <x-filament::icon-button icon="rh-chev-r" color="gray" label="Próximo mês" wire:click="mudarMes(1)" />
            <h2 class="rh-cal-hd__mes">{{ $this->nomeDoMes() }}</h2>
            <x-filament::button color="gray" size="sm" wire:click="hoje">Hoje</x-filament::button>
        </div>

        <div class="rh-legend">
            <span><i class="rh-dot rh-dot--tour"></i>Jogo</span>
            <span><i class="rh-dot rh-dot--rent"></i>Mesa alugada</span>
            <span><i class="rh-dot rh-dot--res"></i>Reserva</span>
            <span><i class="rh-dot rh-dot--block"></i>Bloqueio</span>
        </div>
    </div>

    <div class="rh-cal-scroll">
        <div class="rh-cal" wire:loading.class="is-carregando" wire:target="mudarMes, hoje">
            @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $diaDaSemana)
                <div class="rh-cal__dow">{{ $diaDaSemana }}</div>
            @endforeach

            @foreach ($this->semanas() as $semana)
                @foreach ($semana as $dia)
                    <div
                        @class(['rh-cal__day', 'is-out' => $dia['fora'], 'is-today' => $dia['hoje'], 'is-clicavel' => $podeCriar])
                        @if ($podeCriar)
                            wire:click="mountAction('novoEvento', { data: '{{ $dia['data']->toDateString() }}' })"
                            title="Novo evento em {{ $dia['data']->format('d/m') }}"
                        @endif
                        wire:key="dia-{{ $dia['data']->toDateString() }}"
                    >
                        <span class="rh-cal__n">{{ $dia['data']->day }}</span>

                        @foreach ($dia['eventos'] as $evento)
                            @php
                                $eAluguel = $evento->tipo === \App\Enums\TipoEvento::Aluguel;
                                $rascunho = $evento->jogo?->status === \App\Enums\StatusJogo::Rascunho;
                                $titulo = $eAluguel ? 'Mesa alugada' : ($evento->jogo ? '♠ ' : '').$evento->nome;
                                $detalhe = $eAluguel
                                    ? collect([\App\Support\Datas::hora($evento->hora_inicio), $evento->aluguel?->cliente])->filter()->join(' · ')
                                    : mb_strtolower($evento->horario()).($rascunho ? ' · rascunho' : '');
                            @endphp
                            <button
                                type="button"
                                @class(['rh-ev', $classes[$evento->tipo->value], 'is-rascunho' => $rascunho])
                                wire:click.stop="mountAction('{{ $this->acaoDoEvento($evento) }}', { evento: {{ $evento->id }} })"
                                wire:key="evento-{{ $evento->id }}"
                                title="{{ $evento->nome }} · {{ $evento->horario() }} · {{ $evento->nomesDasMesas() }}"
                            >
                                <b>{{ $titulo }}</b>
                                <span>{{ $detalhe }}</span>
                            </button>
                        @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
