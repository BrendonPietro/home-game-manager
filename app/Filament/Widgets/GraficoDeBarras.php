<?php

namespace App\Filament\Widgets;

use App\Support\Dinheiro;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Gráfico de barras dos últimos jogos (rodapé das Métricas) em CSS (`rh-bars`), como o protótipo: barras felt-300, a
 * última em vermelho, valor em cima e dica com o nome ao passar o mouse. Sem Chart.js.
 */
abstract class GraficoDeBarras extends Widget
{
    protected string $view = 'filament.painel.widgets.grafico';

    protected static bool $isLazy = false;

    /** Permissão do painel que mostra o gráfico. */
    protected const PERMISSAO = 'jogos.ver';

    /** Em dinheiro (centavos), o valor vira "2,1k" e a dica "R$ 2.100". */
    protected const DINHEIRO = false;

    abstract protected function titulo(): string;

    protected function descricao(): ?string
    {
        return null;
    }

    /** @return list<array{rotulo: string, valor: int, dica: string}> do mais antigo para o mais recente */
    abstract protected function barras(): array;

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can(static::PERMISSAO) ?? false;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $barras = $this->barras();
        $maior = max(1, ...array_column($barras, 'valor') ?: [1]);

        return [
            'titulo' => $this->titulo(),
            'descricao' => $this->descricao(),
            'barras' => array_map(fn (array $barra): array => $barra + [
                'altura' => round($barra['valor'] / $maior * 82, 1),
                'texto' => static::DINHEIRO ? self::curto($barra['valor']) : (string) $barra['valor'],
                'completo' => static::DINHEIRO ? Dinheiro::brlCentavos($barra['valor']) : (string) $barra['valor'],
            ], $barras),
        ];
    }

    /** 210000 centavos → "2,1k"; abaixo de mil reais, "R$ 950" sem o R$: "950". */
    private static function curto(int $centavos): string
    {
        $reais = intdiv($centavos, 100);

        return $reais >= 1000
            ? rtrim(rtrim(number_format($reais / 1000, 1, ',', ''), '0'), ',').'k'
            : (string) $reais;
    }
}
