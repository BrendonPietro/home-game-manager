<?php

namespace App\Filament\Widgets;

use App\Services\Painel\Indicadores;

class JogosPorMes extends GraficoDeBarras
{
    protected function titulo(): string
    {
        return 'Jogos por mês';
    }

    protected function barras(): array
    {
        return array_map(
            fn (array $mes): array => ['rotulo' => $mes['rotulo'], 'valor' => $mes['total'], 'dica' => $mes['rotulo']],
            app(Indicadores::class)->jogosPorMes(today()),
        );
    }
}
