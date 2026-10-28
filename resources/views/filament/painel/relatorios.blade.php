{{-- Tela 24: relatórios (App\Filament\Pages\Relatorios). Estilos rh-relatorio / rh-stat no tema do painel. --}}
@php
    use App\Services\Relatorios\Relatorios;

    $relatorio = $this->relatorioAtual();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        {{ $this->filtrosForm }}
    </x-filament::section>

    <div class="rh-relatorio__tipos" role="group" aria-label="Relatório">
        @foreach (Relatorios::TIPOS as $tipo => [$titulo, $descricao, $icone])
            <button
                type="button"
                class="rh-pick"
                aria-pressed="{{ $tipo === $this->relatorio ? 'true' : 'false' }}"
                wire:click="escolher('{{ $tipo }}')"
                wire:key="tipo-{{ $tipo }}"
            >
                @svg($icone, 'rh-ic rh-pick__ic')
                <span class="rh-pick__t">{{ $titulo }}</span>
                <span class="rh-pick__s">{{ $descricao }}</span>
            </button>
        @endforeach
    </div>

    <div class="rh-dash" wire:loading.class="is-carregando">
        @if ($relatorio->resumo)
            <div class="rh-dash__linha">
                @foreach ($relatorio->resumo as $cartao)
                    <div class="rh-stat">
                        <span class="rh-label">{{ $cartao['rotulo'] }}</span>
                        <span class="rh-stat__v">{{ $cartao['valor'] }}</span>
                        @isset($cartao['detalhe'])
                            <span class="rh-stat__s">{{ $cartao['detalhe'] }}</span>
                        @endisset
                    </div>
                @endforeach
            </div>
        @endif

        <x-filament::section :heading="$relatorio->titulo.' — '.$relatorio->periodo" :description="$relatorio->aviso">
            @if ($relatorio->linhas === [])
                <p class="rh-grafico-vazio">Nada neste período.</p>
            @else
                <div class="rh-relatorio__rolagem">
                    <table class="rh-tabela rh-tabela--relatorio">
                        <thead>
                            <tr>
                                @foreach ($relatorio->colunas as $coluna)
                                    <th @class(['n' => $coluna->numerica()])>{{ $coluna->rotulo }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($relatorio->linhas as $linha)
                                <tr>
                                    @foreach ($relatorio->colunas as $coluna)
                                        @php $valor = $linha[$coluna->chave] ?? null; @endphp
                                        <td @class([
                                            'n' => $coluna->numerica(),
                                            'rh-pos' => $coluna->tipo === 'resultado' && $valor > 0,
                                            'rh-neg' => $coluna->tipo === 'resultado' && $valor < 0,
                                        ])>{{ $coluna->formatar($valor) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
