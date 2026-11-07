{{-- Painel de controle: App\Filament\Widgets\ProximoJogo, o jogo em destaque. Estilos rh-destaque no tema do painel. --}}
@php
    use App\Enums\StatusJogo;
    use App\Support\Datas;

    $aoVivo = $proximo?->status === StatusJogo::EmAndamento;
@endphp

<x-filament-widgets::widget class="rh-wi-cheio">
    <div class="rh-destaque">
        @if ($proximo)
            @php
                $tipo = $proximo->eTorneio() ? 'torneio' : 'cash game';
                [$passos, $atual] = \App\Filament\Widgets\ProximoJogo::etapas($proximo);
            @endphp

            <div class="rh-destaque__topo">
                <div class="rh-datebox rh-datebox--lg">
                    <span>{{ Datas::mes($proximo->data) }}</span>
                    <b>{{ $proximo->data->format('d') }}</b>
                </div>
                <div class="rh-destaque__titulo">
                    <span class="rh-label">
                        {{ $aoVivo ? 'Em andamento' : 'Próximo '.$tipo.' · '.mb_strtolower(Datas::quando($proximo->data)) }}
                    </span>
                    <span class="rh-destaque__nome">{{ $proximo->nome }}</span>
                    <span class="rh-stat__s">{{ collect([Datas::porExtenso($proximo->data), Datas::hora($proximo->horario)])->filter()->join(' · ') }}</span>
                </div>
                <div class="rh-destaque__acoes">
                    <x-filament::button tag="a" :href="$this->urlDoJogo($proximo)" color="gray">
                        {{ $podeEditar ? 'Editar' : 'Ver' }}
                    </x-filament::button>
                    @if ($site = $this->urlNoSite($proximo))
                        <x-filament::button tag="a" :href="$site">{{ $aoVivo ? 'Abrir a noite' : 'Abrir no site' }}</x-filament::button>
                    @endif
                </div>
            </div>

            <ol class="rh-etapas" aria-label="Onde a noite está">
                @foreach ($passos as $passo)
                    <li @class(['is-feito' => $loop->index < $atual, 'is-atual' => $loop->index === $atual]) @if ($loop->index === $atual) aria-current="step" @endif>
                        <span class="rh-etapas__ponto" aria-hidden="true"></span>{{ $passo }}
                    </li>
                @endforeach
            </ol>

            <dl class="rh-destaque__numeros">
                @foreach ($numeros as [$rotulo, $valor])
                    <div>
                        <dt class="rh-label">{{ $rotulo }}</dt>
                        <dd title="{{ $valor }}">{{ $valor }}</dd>
                    </div>
                @endforeach
            </dl>
        @else
            <div class="rh-destaque__topo">
                <div class="rh-destaque__titulo">
                    <span class="rh-label">Próximo jogo</span>
                    <span class="rh-destaque__nome">Nenhum jogo agendado</span>
                    <span class="rh-stat__s">
                        {{ $rascunhos
                            ? ($rascunhos === 1 ? '1 jogo em rascunho' : "{$rascunhos} jogos em rascunho").': ele entra aqui depois de agendado'
                            : 'Jogo em rascunho entra aqui depois de agendado' }}
                    </span>
                </div>
                <div class="rh-destaque__acoes">
                    @if ($rascunhos)
                        <x-filament::button tag="a" :href="$this->urlDosJogos()" color="gray">Ver jogos</x-filament::button>
                    @endif
                    @if ($podeCriar)
                        <x-filament::button tag="a" :href="$this->novoJogoUrl()" icon="rh-plus">Novo jogo</x-filament::button>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
