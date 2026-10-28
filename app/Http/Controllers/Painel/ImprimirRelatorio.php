<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Relatorios\Filtro;
use App\Services\Relatorios\Relatorios;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * "Exportar PDF" dos relatórios (tela 24): o mesmo relatório numa página clara para imprimir. Com
 * `imprimir=1` (o botão manda) já abre o diálogo do navegador (Salvar como PDF). Sem dompdf.
 * Rota autenticada do painel, `relatorios.exportar`.
 */
class ImprimirRelatorio extends Controller
{
    public function __invoke(Request $request, Relatorios $relatorios): View
    {
        abort_unless(Filament::auth()->user()?->can('relatorios.exportar'), 403);

        $tipo = (string) $request->query('relatorio', 'premiacoes');
        abort_unless(array_key_exists($tipo, Relatorios::TIPOS), 404);

        $campos = $request->only(['periodo', 'de', 'ate', 'jogo', 'jogador']);
        $relatorio = $relatorios->gerar($tipo, Filtro::doFormulario($campos));

        $jogo = filled($campos['jogo'] ?? null) ? Jogo::query()->find($campos['jogo']) : null;
        $jogador = filled($campos['jogador'] ?? null) ? Usuario::query()->withTrashed()->find($campos['jogador']) : null;

        return view('filament.painel.relatorio-impressao', [
            'relatorio' => $relatorio,
            'filtros' => array_filter([
                'Jogo' => $jogo?->nome,
                'Jogador' => $jogador?->nome,
            ]),
            'gerado' => now(),
            'paisagem' => count($relatorio->colunas) > 6,
            'imprimir' => $request->boolean('imprimir'),
        ]);
    }
}
