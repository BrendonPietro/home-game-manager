<?php

namespace App\Filament\Widgets;

use App\Services\Painel\Indicadores;

class ArrecadacaoPorJogo extends GraficoDeBarras
{
    protected const PERMISSAO = 'financeiro.ver';

    protected const DINHEIRO = true;

    protected function titulo(): string
    {
        return 'Arrecadação por jogo';
    }

    protected function descricao(): ?string
    {
        return 'últimos 8 finalizados';
    }

    protected function barras(): array
    {
        return app(Indicadores::class)->ultimosJogos()
            ->map(fn (array $j): array => ['rotulo' => $j['rotulo'], 'valor' => $j['arrecadacao'], 'dica' => $j['jogo']->nome])
            ->all();
    }
}
