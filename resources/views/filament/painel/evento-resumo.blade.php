{{-- Resumo de um evento do calendário (modal verEvento da tela 17). --}}
@php
    use App\Enums\StatusAluguel;
    use App\Enums\StatusJogo;
    use App\Support\Datas;
    use App\Support\Dinheiro;

    $jogo = $evento->jogo;
    $aluguel = $evento->aluguel;
    $buyin = $jogo?->buyinPadrao();
    $linhas = array_filter([
        'Status' => $jogo?->status->label() ?? $aluguel?->status->label(),
        'Confirmados' => $jogo ? (string) $jogo->participantes_count : null,
        'Buy-in' => $buyin ? Dinheiro::brl($buyin->valor) : null,
        'Cliente' => $aluguel?->cliente,
        'Valor' => $aluguel ? Dinheiro::brl($aluguel->valor) : null,
        'Pago em' => $aluguel?->pago_em?->format('d/m/Y'),
        'Responsável' => $evento->responsavel?->nome,
    ], fn ($valor) => filled($valor));
    $cor = match (true) {
        $jogo !== null => match ($jogo->status) {
            StatusJogo::EmAndamento => 'danger',
            StatusJogo::Finalizado => 'success',
            StatusJogo::Rascunho => 'gray',
            default => 'info',
        },
        $aluguel !== null => match ($aluguel->status) {
            StatusAluguel::Pago => 'success',
            StatusAluguel::Confirmado => 'info',
            default => 'warning',
        },
        default => 'gray',
    };
@endphp

<div class="rh-resumo">
    <div class="rh-resumo__topo">
        <x-filament::badge :color="$cor">
            {{ $jogo ? ($jogo->eTorneio() ? '♠ Torneio' : '♠ Cash game') : ($aluguel ? 'Mesa alugada' : $evento->tipo->label()) }}
        </x-filament::badge>
        <span class="rh-resumo__quando">
            {{ Datas::curta($evento->data) }} · {{ $evento->horario() }} · {{ $evento->nomesDasMesas() }}
        </span>
    </div>

    @if ($linhas)
        <dl class="rh-kv">
            @foreach ($linhas as $rotulo => $valor)
                <dt>{{ $rotulo }}</dt>
                <dd>{{ $valor }}</dd>
            @endforeach
        </dl>
    @endif

    @if (filled($evento->observacoes))
        <p class="rh-resumo__obs">{{ $evento->observacoes }}</p>
    @endif
</div>
