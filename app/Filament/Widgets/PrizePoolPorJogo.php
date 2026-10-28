<?php

namespace App\Filament\Widgets;

use App\Services\Painel\Indicadores;

class PrizePoolPorJogo extends GraficoDeBarras
{
    protected const PERMISSAO = 'financeiro.ver';

    protected const DINHEIRO = true;

    protected function titulo(): string
    {
        return 'Prize pool por jogo';
    }

    /** "90% da arrecadação" (média dos jogos do gráfico) */
    protected function descricao(): ?string
    {
        $jogos = app(Indicadores::class)->ultimosJogos();
        $arrecadacao = $jogos->sum('arrecadacao');

        return $arrecadacao > 0 ? round($jogos->sum('prizePool') / $arrecadacao * 100).'% da arrecadação' : null;
    }

    protected function barras(): array
    {
        return app(Indicadores::class)->ultimosJogos()
            ->map(fn (array $j): array => ['rotulo' => $j['rotulo'], 'valor' => $j['prizePool'], 'dica' => $j['jogo']->nome])
            ->all();
    }
}
