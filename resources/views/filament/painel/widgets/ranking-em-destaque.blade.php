{{-- Painel de controle: App\Filament\Widgets\RankingEmDestaque. Estilos rh-ranking no tema do painel. --}}
@php
    $pontos = fn (?int $n): string => ($n ?? 0) === 1 ? '1 ponto' : number_format($n ?? 0, 0, ',', '.').' pontos';
    $plural = fn (int $n, string $um, string $varios): string => $n === 1 ? "1 {$um}" : "{$n} {$varios}";
@endphp

<x-filament-widgets::widget class="rh-wi-cheio">
    @if ($destaque)
        @php
            $ranking = $destaque['ranking'];
            $lider = $destaque['classificacao']->first();
        @endphp
        <x-filament::section :heading="$ranking->nome" :description="'Ranking em destaque · '.\App\Filament\Widgets\RankingEmDestaque::periodo($ranking)">
            <x-slot name="afterHeader">
                <x-filament::link :href="$this->urlDaClassificacao($ranking)" icon="rh-chevron-direita" icon-position="after" size="sm">Classificação</x-filament::link>
            </x-slot>

            @if (! $lider)
                <p class="rh-grafico-vazio">Ninguém pontuou ainda. Os pontos entram quando um jogo que vale neste ranking é finalizado.</p>
            @else
                <div class="rh-ranking">
                    <div class="rh-ranking__lider">
                        <x-filament::icon icon="rh-trofeu" class="rh-ranking__trofeu" />
                        <div class="rh-ranking__quem">
                            <span class="rh-label">1º lugar</span>
                            <span class="rh-ranking__nome">{{ $lider->nome_exibicao }}</span>
                            <span class="rh-stat__s">{{ collect([$plural((int) $lider->vitorias, 'vitória', 'vitórias'), $plural((int) $lider->jogos, 'jogo', 'jogos')])->join(' · ') }}</span>
                        </div>
                        <span class="rh-ranking__pontos">{{ $pontos((int) $lider->pontos) }}</span>
                    </div>

                    @if ($destaque['classificacao']->count() > 1)
                        <ol class="rh-ranking__lista">
                            @foreach ($destaque['classificacao']->skip(1) as $usuario)
                                <li wire:key="ranking-{{ $usuario->id }}">
                                    <span class="rh-ranking__pos">{{ $loop->iteration + 1 }}º</span>
                                    <span class="rh-ranking__nome-lista">{{ $usuario->nome_exibicao }}</span>
                                    <span class="rh-ranking__pts">{{ $pontos((int) $usuario->pontos) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endif

            @if ($destaque['outros']->isNotEmpty())
                <p class="rh-destaque__rodape">
                    Também valendo:
                    @foreach ($destaque['outros'] as $outro)
                        <x-filament::link :href="$this->urlDaClassificacao($outro)" size="sm">{{ $outro->nome }}</x-filament::link>{{ $loop->last ? '' : ',' }}
                    @endforeach
                </p>
            @endif
        </x-filament::section>
    @else
        <x-filament::section heading="Ranking">
            <x-slot name="afterHeader">
                <x-filament::link :href="$rankings" icon="rh-chevron-direita" icon-position="after" size="sm">Rankings</x-filament::link>
            </x-slot>
            <p class="rh-grafico-vazio">Nenhum ranking valendo agora. {{ $podeCriar ? 'Crie um em Cadastros → Rankings, com o período em que ele vale.' : '' }}</p>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
