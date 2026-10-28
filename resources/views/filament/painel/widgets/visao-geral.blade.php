{{-- Topo do dashboard (tela 16): App\Filament\Widgets\VisaoGeral. Estilos rh-dash / rh-stat no tema do painel. --}}
@php
    use App\Enums\StatusJogo;
    use App\Support\Datas;
    use App\Support\Dinheiro;
    use Illuminate\Support\Str;

    $plural = fn (int $n, string $um, string $varios): string => $n === 1 ? "1 {$um}" : "{$n} {$varios}";
    $nomeDoMes = $mes->translatedFormat('F');
@endphp

<x-filament-widgets::widget class="rh-dash">
    <div class="rh-dash__linha rh-dash__linha--topo">
        @if ($podeVerJogos)
            <div class="rh-stat rh-stat--proximo">
                @if ($proximo)
                    <div class="rh-datebox">
                        <span>{{ Datas::mes($proximo->data) }}</span>
                        <b>{{ $proximo->data->format('d') }}</b>
                    </div>
                    <div class="rh-proximo">
                        <span class="rh-label">
                            {{ $proximo->status === StatusJogo::EmAndamento ? 'Em andamento' : ($proximo->eTorneio() ? 'Próximo torneio' : 'Próximo cash game') }}
                        </span>
                        <span class="rh-proximo__nome">{{ $proximo->nome }}</span>
                        <span class="rh-stat__s">
                            {{ collect([
                                Str::ucfirst($proximo->data->translatedFormat('D')),
                                Datas::hora($proximo->horario),
                                $plural($proximo->participantes_count, 'confirmado', 'confirmados'),
                                $plural($proximo->mesas_count, 'mesa', 'mesas'),
                            ])->filter()->join(' · ') }}
                        </span>
                    </div>
                    <div class="rh-proximo__acoes">
                        <x-filament::button tag="a" :href="$this->urlDoJogo($proximo)" color="gray" size="sm">
                            {{ $podeEditarProximo ? 'Editar' : 'Ver' }}
                        </x-filament::button>
                        @if ($site = $this->urlNoSite($proximo))
                            <x-filament::button tag="a" :href="$site" size="sm">Abrir no site</x-filament::button>
                        @endif
                    </div>
                @else
                    <div class="rh-proximo">
                        <span class="rh-label">Próximo jogo</span>
                        <span class="rh-proximo__nome">Nenhum jogo agendado</span>
                        <span class="rh-stat__s">Jogo em rascunho entra aqui depois de agendado.</span>
                    </div>
                    @if ($podeCriarJogo)
                        <div class="rh-proximo__acoes">
                            <x-filament::button tag="a" :href="$this->novoJogoUrl()" size="sm" icon="rh-plus">Novo jogo</x-filament::button>
                        </div>
                    @endif
                @endif
            </div>
        @endif

        <div class="rh-stat">
            <span class="rh-label">Jogadores cadastrados</span>
            <span class="rh-stat__v">{{ $jogadores['total'] }}</span>
            <span class="rh-stat__s">{{ $plural($jogadores['ativos'], 'ativo', 'ativos') }} · {{ $plural($jogadores['total'] - $jogadores['ativos'], 'inativo', 'inativos') }}</span>
        </div>

        <div class="rh-stat">
            <span class="rh-label">Jogos no mês</span>
            <span class="rh-stat__v">{{ $jogos['total'] }}</span>
            <span class="rh-stat__s">{{ $nomeDoMes }} · {{ $jogos['hoje'] ? $plural($jogos['hoje'], 'hoje', 'hoje') : $plural($jogos['finalizados'], 'finalizado', 'finalizados') }}</span>
        </div>

        <div class="rh-stat">
            <span class="rh-label">Mesas disponíveis</span>
            <span class="rh-stat__v">{{ $mesas['ativas'] }} / {{ $mesas['total'] }}</span>
            <span class="rh-stat__s">
                {{ match (true) {
                    $mesas['total'] === 0 => 'nenhuma cadastrada',
                    $mesas['inativas'] === [] => 'todas ativas',
                    default => collect($mesas['inativas'])->join(', ', ' e ').(count($mesas['inativas']) === 1 ? ' inativa' : ' inativas'),
                } }}
            </span>
        </div>
    </div>

    @if ($financeiro)
        <div class="rh-dash__linha">
            <div class="rh-stat">
                <span class="rh-label">Arrecadação no mês</span>
                <span class="rh-stat__v">{{ Dinheiro::brlCentavos($financeiro['arrecadacao']) }}</span>
                <span class="rh-stat__s">{{ $nomeDoMes }} · {{ $plural($financeiro['jogos'], 'jogo', 'jogos') }}</span>
            </div>

            <div class="rh-stat">
                <span class="rh-label">Prize pools no mês</span>
                <span class="rh-stat__v">{{ Dinheiro::brlCentavos($financeiro['prizePools']) }}</span>
                <span class="rh-stat__s">pagos aos jogadores</span>
            </div>

            <div class="rh-stat">
                <span class="rh-label">Aluguéis no mês</span>
                <span class="rh-stat__v">{{ Dinheiro::brlCentavos($financeiro['alugueis']) }}</span>
                <span class="rh-stat__s">{{ $financeiro['alugueisPagos'] ? $plural($financeiro['alugueisPagos'], 'aluguel pago', 'aluguéis pagos') : 'nenhum pago' }}</span>
            </div>

            @php
                $devedores = $pendencias['jogadores']->where('saldo', '>', 0)->count();
                $alugueis = $pendencias['alugueis']->count();
                $emAberto = $pendencias['jogadores']->isNotEmpty() || $alugueis > 0;
            @endphp
            <div @class(['rh-stat', 'rh-stat--pendente' => $emAberto])>
                <span class="rh-label">Valores pendentes</span>
                <span class="rh-stat__v">{{ Dinheiro::brlCentavos($pendencias['aReceber']) }}</span>
                <span class="rh-stat__s">
                    @if ($emAberto)
                        {{ collect([
                            $devedores ? $plural($devedores, 'jogador', 'jogadores') : null,
                            $alugueis ? $plural($alugueis, 'aluguel', 'aluguéis') : null,
                        ])->filter()->join(' · ') ?: 'nada' }} a receber{{ $pendencias['aPagar'] ? ' · caixa deve '.Dinheiro::brlCentavos($pendencias['aPagar']) : '' }} · {{ $this->pendenciasAction }}
                    @else
                        nada em aberto
                    @endif
                </span>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-widgets::widget>
