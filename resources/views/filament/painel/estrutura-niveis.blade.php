{{-- Níveis de uma estrutura de blinds (EstruturaBlinds::linhas): prévia no cadastro da estrutura e aba Blinds do jogo. --}}
@php
    use App\Models\EstruturaBlinds;

    $total = array_sum(array_column($linhas, 'minutos'));
@endphp

@if ($linhas)
    <div class="rh-relatorio__rolagem">
        <table class="rh-tabela rh-tabela--relatorio rh-tabela--niveis">
            <thead>
                <tr>
                    <th>Nível</th>
                    <th>Blinds</th>
                    <th class="n">Ante</th>
                    <th class="n">Duração</th>
                    <th class="n">Começa em</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($linhas as $linha)
                    <tr @class(['is-intervalo' => $linha['intervalo']])>
                        @if ($linha['intervalo'])
                            <td colspan="3">{{ $linha['blinds'] }}</td>
                        @else
                            <td>{{ $linha['numero'] }}</td>
                            <td>{{ $linha['blinds'] }}</td>
                            <td class="n">{{ $linha['ante'] ?? '—' }}</td>
                        @endif
                        <td class="n">{{ $linha['minutos'] }} min</td>
                        <td class="n">{{ $linha['comeca'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Total</td>
                    <td class="n">{{ EstruturaBlinds::duracao($total) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
@else
    <p class="rh-muted">Sem níveis.</p>
@endif
