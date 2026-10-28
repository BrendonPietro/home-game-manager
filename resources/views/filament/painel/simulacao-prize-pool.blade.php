{{-- Resultado da simulação do prize pool no cadastro do jogo (JogoForm::simulacao). Valores em centavos. --}}
@php
    use App\Support\Dinheiro;
@endphp

<div class="rh-simulacao">
    <dl class="rh-kv">
        <dt>Arrecadação</dt>
        <dd>{{ Dinheiro::brlCentavos($resumo->arrecadacao) }}</dd>
        @if ($resumo->foraDaPremiacao > 0)
            <dt>Fora da premiação</dt>
            <dd>{{ Dinheiro::brlCentavos($resumo->foraDaPremiacao) }}</dd>
        @endif
        @foreach ($resumo->taxas as $taxa)
            <dt>{{ $taxa['nome'] }}</dt>
            <dd>{{ Dinheiro::brlCentavos(-$taxa['valor'], true) }}</dd>
        @endforeach
        <dt class="rh-simulacao__pool">Prize pool</dt>
        <dd class="rh-simulacao__pool">{{ Dinheiro::brlCentavos($resumo->prizePool) }}</dd>
    </dl>

    @if ($torneio)
        @if ($resumo->premios)
            <ol class="rh-simulacao__premios">
                @foreach ($resumo->premios as $premio)
                    <li>
                        <span>{{ $premio['posicao'] }}º · {{ str_replace('.', ',', rtrim(rtrim($premio['percentual'], '0'), '.')) }}%</span>
                        <b>{{ Dinheiro::brlCentavos($premio['valor']) }}</b>
                    </li>
                @endforeach
            </ol>
        @else
            <p class="rh-muted">Sem faixa de premiação para {{ $resumo->jogadores }} jogadores (veja Configurações ou a premiação manual).</p>
        @endif
    @endif
</div>
