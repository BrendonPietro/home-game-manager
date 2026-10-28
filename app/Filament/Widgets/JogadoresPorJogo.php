<?php

namespace App\Filament\Widgets;

use App\Services\Painel\Indicadores;

class JogadoresPorJogo extends GraficoDeBarras
{
    protected function titulo(): string
    {
        return 'Jogadores por jogo';
    }

    protected function barras(): array
    {
        return app(Indicadores::class)->ultimosJogos()
            ->map(fn (array $j): array => ['rotulo' => $j['rotulo'], 'valor' => $j['jogadores'], 'dica' => $j['jogo']->nome])
            ->all();
    }
}
