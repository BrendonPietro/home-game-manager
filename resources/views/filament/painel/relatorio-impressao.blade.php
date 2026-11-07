{{-- "Exportar PDF" dos relatórios (App\Http\Controllers\Painel\ImprimirRelatorio): página clara, sem o painel em volta, que abre o diálogo de impressão. --}}
@php
    use Illuminate\Support\Facades\Vite;

    $fonte = fn (string $arquivo): string => Vite::asset('resources/fonts/barlow/'.$arquivo);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $relatorio->arquivo() }}</title>
    <x-icones />
    <x-fontes />
    <style>
        @font-face { font-family: 'Barlow'; src: url('{{ $fonte('Barlow-400.woff2') }}') format('woff2'); font-weight: 400; }
        @font-face { font-family: 'Barlow'; src: url('{{ $fonte('Barlow-600.woff2') }}') format('woff2'); font-weight: 600; }
        @font-face { font-family: 'Barlow Condensed'; src: url('{{ $fonte('BarlowCondensed-600.woff2') }}') format('woff2'); font-weight: 600; }
        @font-face { font-family: 'Barlow Condensed'; src: url('{{ $fonte('BarlowCondensed-700.woff2') }}') format('woff2'); font-weight: 700; }

        @page { size: A4 {{ $paisagem ? 'landscape' : 'portrait' }}; margin: 12mm; }

        :root {
            --tinta: #0b0c0d;
            --tinta-2: #646c71;
            --linha: #dcd7cf;
            --fundo: #f7f5f1;
            --vermelho: #c62f3a;
            --verde: #1f7a4f;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            background: #ffffff;
            color: var(--tinta);
            font: 400 12px/1.4 'Barlow', Arial, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .barra {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-bottom: 16px;
        }

        .barra button {
            padding: 8px 14px;
            border: 1px solid var(--linha);
            border-radius: 5px;
            background: #ffffff;
            font: 600 13px 'Barlow', Arial, sans-serif;
            cursor: pointer;
        }

        .barra button.principal {
            border-color: var(--vermelho);
            background: var(--vermelho);
            color: #ffffff;
        }

        header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--vermelho);
        }

        .marca {
            font: 700 14px 'Barlow Condensed', 'Arial Narrow', sans-serif;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .marca span { color: var(--vermelho); }

        h1 {
            margin: 2px 0 0;
            font: 700 24px/1.1 'Barlow Condensed', 'Arial Narrow', sans-serif;
        }

        .meta {
            color: var(--tinta-2);
            text-align: right;
        }

        .aviso {
            margin: 10px 0 0;
            color: var(--tinta-2);
        }

        .resumo {
            display: grid;
            grid-template-columns: repeat({{ max(1, count($relatorio->resumo)) }}, minmax(0, 1fr));
            gap: 8px;
            margin: 14px 0;
        }

        .cartao {
            padding: 8px 10px;
            background: var(--fundo);
            border: 1px solid var(--linha);
            border-radius: 5px;
        }

        .cartao b {
            display: block;
            font: 700 18px 'Barlow Condensed', 'Arial Narrow', sans-serif;
            font-variant-numeric: tabular-nums;
        }

        .rotulo {
            font: 600 10px 'Barlow Condensed', 'Arial Narrow', sans-serif;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--tinta-2);
        }

        .detalhe { color: var(--tinta-2); }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead { display: table-header-group; }

        th {
            padding: 6px 8px;
            background: var(--fundo);
            border-bottom: 1px solid var(--linha);
            font: 600 10px 'Barlow Condensed', 'Arial Narrow', sans-serif;
            letter-spacing: 0.08em;
            text-align: left;
            text-transform: uppercase;
            color: var(--tinta-2);
        }

        td {
            padding: 5px 8px;
            border-bottom: 1px solid var(--linha);
        }

        tr { break-inside: avoid; }

        .n {
            text-align: right;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .pos { color: var(--verde); }

        .neg { color: var(--vermelho); }

        .vazio {
            padding: 24px 0;
            color: var(--tinta-2);
        }

        footer {
            margin-top: 12px;
            color: var(--tinta-2);
            font-size: 10px;
        }

        @media print {
            body { padding: 0; }
            .barra { display: none; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <button type="button" onclick="window.close()">Fechar</button>
        <button type="button" class="principal" onclick="window.print()">Imprimir / salvar PDF</button>
    </div>

    <header>
        <div>
            <div class="marca"><span>♠</span> Rodrigues Home Game</div>
            <h1>{{ $relatorio->titulo }} — {{ $relatorio->periodo }}</h1>
        </div>
        <div class="meta">
            @foreach ($filtros as $rotulo => $valor)
                <div>{{ $rotulo }}: {{ $valor }}</div>
            @endforeach
            <div>Gerado em {{ $gerado->format('d/m/Y H:i') }}</div>
        </div>
    </header>

    @if ($relatorio->aviso)
        <p class="aviso">{{ $relatorio->aviso }}</p>
    @endif

    @if ($relatorio->resumo)
        <div class="resumo">
            @foreach ($relatorio->resumo as $cartao)
                <div class="cartao">
                    <span class="rotulo">{{ $cartao['rotulo'] }}</span>
                    <b>{{ $cartao['valor'] }}</b>
                    @isset($cartao['detalhe'])
                        <span class="detalhe">{{ $cartao['detalhe'] }}</span>
                    @endisset
                </div>
            @endforeach
        </div>
    @endif

    @if ($relatorio->linhas === [])
        <p class="vazio">Nada neste período.</p>
    @else
        <table>
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
                                'pos' => $coluna->tipo === 'resultado' && $valor > 0,
                                'neg' => $coluna->tipo === 'resultado' && $valor < 0,
                            ])>{{ $coluna->formatar($valor) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <footer>{{ count($relatorio->linhas) === 1 ? '1 linha' : count($relatorio->linhas).' linhas' }}</footer>

    @if ($imprimir)
        <script>
            // Vindo do "Exportar PDF": abre o diálogo de impressão sozinho ("Salvar como PDF" é o destino do navegador).
            window.addEventListener('load', () => setTimeout(() => window.print(), 300));
        </script>
    @endif
</body>
</html>
