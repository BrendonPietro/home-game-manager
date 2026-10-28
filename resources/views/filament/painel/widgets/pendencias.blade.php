{{-- Lista dos valores pendentes (modal do VisaoGeral). Saldo positivo: o jogador deve ao caixa. --}}
@php
    use App\Filament\Resources\Alugueis\AluguelResource;
    use App\Services\Painel\Indicadores;
    use App\Support\EtapaDoJogo;
    use App\Support\Dinheiro;
@endphp

<div class="rh-pendencias">
    @if ($pendencias['jogadores']->isNotEmpty())
        <section>
            <h3 class="rh-label">Jogadores</h3>
            <table class="rh-tabela">
                <tbody>
                    @foreach ($pendencias['jogadores'] as $linha)
                        <tr>
                            <td class="rh-muted">{{ Indicadores::rotulo($linha['jogo']) }} · {{ $linha['jogo']->data->format('d/m') }}</td>
                            <td>{{ $linha['participante']->usuario->nome_exibicao }}</td>
                            <td class="rh-muted">{{ $linha['saldo'] > 0 ? 'deve ao caixa' : 'a receber do caixa' }}</td>
                            <td class="n">{{ Dinheiro::brlCentavos(abs($linha['saldo'])) }}</td>
                            <td class="n">
                                @if ($url = EtapaDoJogo::url($linha['jogo'], 'fechamento'))
                                    <x-filament::link :href="$url" size="sm">Fechamento</x-filament::link>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($pendencias['alugueis']->isNotEmpty())
        <section>
            <h3 class="rh-label">Aluguéis</h3>
            <table class="rh-tabela">
                <tbody>
                    @foreach ($pendencias['alugueis'] as $aluguel)
                        <tr>
                            <td class="rh-muted">{{ $aluguel->evento->data->format('d/m') }}</td>
                            <td>{{ $aluguel->cliente }}</td>
                            <td class="rh-muted">{{ $aluguel->status->label() }}</td>
                            <td class="n">{{ Dinheiro::brl($aluguel->valor) }}</td>
                            <td class="n">
                                <x-filament::link :href="AluguelResource::getUrl('index')" size="sm">Aluguéis</x-filament::link>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($pendencias['jogadores']->isEmpty() && $pendencias['alugueis']->isEmpty())
        <p class="rh-muted">Nada em aberto.</p>
    @endif
</div>
