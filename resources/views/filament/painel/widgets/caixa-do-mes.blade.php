{{-- Painel de controle: App\Filament\Widgets\CaixaDoMes (só com financeiro.ver). Estilos rh-dash / rh-stat no tema do painel. --}}
@php
    use App\Support\Dinheiro;

    $plural = fn (int $n, string $um, string $varios): string => $n === 1 ? "1 {$um}" : "{$n} {$varios}";
    $nomeDoMes = $mes->translatedFormat('F');
    $devedores = $pendencias['jogadores']->where('saldo', '>', 0)->count();
    $alugueis = $pendencias['alugueis']->count();
    $emAberto = $pendencias['jogadores']->isNotEmpty() || $alugueis > 0;
@endphp

<x-filament-widgets::widget class="rh-dash">
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

    <x-filament-actions::modals />
</x-filament-widgets::widget>
