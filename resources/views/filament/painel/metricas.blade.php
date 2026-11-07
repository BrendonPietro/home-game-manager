{{-- Dashboards e relatórios → Métricas (App\Filament\Pages\Metricas, Services\Relatorios\Metricas). --}}
@php
    use App\Support\Dinheiro;

    $m = $this->metricas();
    $g = $m['geral'];
    $num = fn (float $n, int $casas = 1): string => number_format($n, $casas, ',', '.');
    $diferenca = fn (int $pct): string => match (true) {
        $pct > 0 => '+'.$pct.'% da média',
        $pct < 0 => '−'.abs($pct).'% da média',
        default => 'na média',
    };
    $comparacoes = [
        ['Por tipo de evento', 'O modelo de onde o jogo saiu (o cadastro do jogo deixa escolher)', $m['porModelo']],
        ['Torneio × cash game', 'No cash game o prize pool é o que está nas fichas', $m['porModalidade']],
        ['Pelo valor do buy-in', 'O buy-in padrão de cada jogo', $m['porBuyin']],
    ];
    // Barras mês a mês: uma medida por gráfico (nunca dois eixos), a mais recente em destaque.
    $barras = function (string $campo, bool $dinheiro) use ($m, $num): array {
        $maior = max(1, ...array_map(fn ($mes) => $mes[$campo], $m['porMes']) ?: [1]);

        return array_map(fn (array $mes): array => [
            'rotulo' => $mes['rotulo'],
            'altura' => round($mes[$campo] / $maior * 82, 1),
            'texto' => $dinheiro ? (string) intdiv($mes[$campo], 100) : $num($mes[$campo]),
            'completo' => $dinheiro ? Dinheiro::brlCentavos($mes[$campo]) : $num($mes[$campo]),
        ], $m['porMes']);
    };
@endphp

<x-filament-panels::page>
    <x-filament::section>
        {{ $this->filtrosForm }}
    </x-filament::section>

    <div class="rh-dash" wire:loading.class="is-carregando">
        @if ($g['jogos'] === 0)
            <x-filament::section>
                <p class="rh-grafico-vazio">Nenhum jogo finalizado em {{ mb_strtolower($m['periodo']) }}.</p>
            </x-filament::section>
        @else
            <div class="rh-dash__linha">
                <div class="rh-stat">
                    <span class="rh-label">Jogos finalizados</span>
                    <span class="rh-stat__v">{{ $g['jogos'] }}</span>
                    <span class="rh-stat__s">{{ $m['periodo'] }}</span>
                </div>
                <div class="rh-stat">
                    <span class="rh-label">Média de jogadores</span>
                    <span class="rh-stat__v">{{ $num($g['mediaJogadores']) }}</span>
                    <span class="rh-stat__s">{{ $g['jogadores'] }} no total</span>
                </div>
                <div class="rh-stat">
                    <span class="rh-label">Média de prize pool</span>
                    <span class="rh-stat__v">{{ Dinheiro::brlCentavos($g['mediaPrizePool']) }}</span>
                    <span class="rh-stat__s">por jogo</span>
                </div>
                <div class="rh-stat">
                    <span class="rh-label">Gasto por jogador</span>
                    <span class="rh-stat__v">{{ Dinheiro::brlCentavos($g['gastoPorJogador']) }}</span>
                    <span class="rh-stat__s">em média, por noite</span>
                </div>
                <div class="rh-stat">
                    <span class="rh-label">Média de arrecadação</span>
                    <span class="rh-stat__v">{{ Dinheiro::brlCentavos($g['mediaArrecadacao']) }}</span>
                    <span class="rh-stat__s">{{ Dinheiro::brlCentavos($g['arrecadacao']) }} no período</span>
                </div>
                <div class="rh-stat">
                    <span class="rh-label">Rebuys por jogador</span>
                    <span class="rh-stat__v">{{ $num($g['rebuysPorJogador'], 2) }}</span>
                    <span class="rh-stat__s">por noite</span>
                </div>
                <div class="rh-stat">
                    <span class="rh-label">Add-ons por jogador</span>
                    <span class="rh-stat__v">{{ $num($g['addonsPorJogador'], 2) }}</span>
                    <span class="rh-stat__s">por noite</span>
                </div>
                <div class="rh-stat">
                    <span class="rh-label">Meses com jogo</span>
                    <span class="rh-stat__v">{{ count($m['porMes']) }}</span>
                    <span class="rh-stat__s">{{ $num($g['jogos'] / max(1, count($m['porMes']))) }} jogos por mês</span>
                </div>
            </div>

            @foreach ($comparacoes as [$titulo, $descricao, $linhas])
                @php $maior = max(1, ...array_column($linhas, 'gastoPorJogador') ?: [1]); @endphp
                <x-filament::section :heading="$titulo" :description="$descricao">
                    <div class="rh-relatorio__rolagem">
                        <table class="rh-tabela rh-tabela--relatorio">
                            <thead>
                                <tr>
                                    <th>Grupo</th>
                                    <th class="n">Jogos</th>
                                    <th class="n">Média de jogadores</th>
                                    <th class="n">Média de prize pool</th>
                                    <th class="n">Gasto por jogador</th>
                                    <th class="n">Comparado à média</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($linhas as $linha)
                                    <tr wire:key="{{ md5($titulo.$linha['grupo']) }}">
                                        <td>{{ $linha['grupo'] }}</td>
                                        <td class="n">{{ $linha['jogos'] }}</td>
                                        <td class="n">{{ $num($linha['mediaJogadores']) }}</td>
                                        <td class="n">{{ Dinheiro::brlCentavos($linha['mediaPrizePool']) }}</td>
                                        <td class="n">
                                            <span class="rh-medida" title="Gasto por jogador: {{ Dinheiro::brlCentavos($linha['gastoPorJogador']) }}">
                                                <span class="rh-medida__trilho" aria-hidden="true"><span class="rh-medida__barra" style="width: {{ round($linha['gastoPorJogador'] / $maior * 100, 1) }}%"></span></span>
                                                {{ Dinheiro::brlCentavos($linha['gastoPorJogador']) }}
                                            </span>
                                        </td>
                                        <td class="n rh-muted">{{ $diferenca($linha['diferenca']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endforeach

            <div class="rh-metricas__meses">
                @foreach ([['Gasto por jogador, mês a mês', 'gastoPorJogador', true], ['Média de jogadores, mês a mês', 'mediaJogadores', false]] as [$titulo, $campo, $dinheiro])
                    <x-filament::section :heading="$titulo">
                        <div class="rh-bars" role="img" aria-label="{{ $titulo }}: {{ collect($barras($campo, $dinheiro))->map(fn ($b) => $b['rotulo'].' '.$b['completo'])->join(', ') }}">
                            @foreach ($barras($campo, $dinheiro) as $barra)
                                <div @class(['rh-bars__c', 'is-hl' => $loop->last])>
                                    <span class="rh-tip">{{ $barra['rotulo'] }}: {{ $barra['completo'] }}</span>
                                    <span class="rh-bars__v">{{ $barra['texto'] }}</span>
                                    <div class="rh-bars__b" style="height: {{ $barra['altura'] }}%"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="rh-bars__x">
                            @foreach ($barras($campo, $dinheiro) as $barra)
                                <span>{{ $barra['rotulo'] }}</span>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
