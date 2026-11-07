<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Rankings\RankingResource;
use App\Models\Ranking;
use App\Services\Painel\Indicadores;
use Filament\Widgets\Widget;

/**
 * Painel de controle, ao lado do próximo jogo: o ranking que vale agora com mais pontos distribuídos,
 * o líder em destaque e os seguintes, e os outros rankings que valem agora (Indicadores::rankingEmDestaque).
 */
class RankingEmDestaque extends Widget
{
    protected string $view = 'filament.painel.widgets.ranking-em-destaque';

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 1];

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return RankingResource::canAccess();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'destaque' => app(Indicadores::class)->rankingEmDestaque(),
            'rankings' => RankingResource::getUrl(),
            'podeCriar' => RankingResource::canCreate(),
        ];
    }

    public function urlDaClassificacao(Ranking $ranking): string
    {
        return RankingResource::getUrl('classificacao', ['record' => $ranking]);
    }

    /** "01/01/2026 a 31/12/2026", "desde 01/10/2026", "até 31/12/2026", "sem período". */
    public static function periodo(Ranking $ranking): string
    {
        return match (true) {
            $ranking->inicio && $ranking->fim => $ranking->inicio->format('d/m/Y').' a '.$ranking->fim->format('d/m/Y'),
            (bool) $ranking->inicio => 'desde '.$ranking->inicio->format('d/m/Y'),
            (bool) $ranking->fim => 'até '.$ranking->fim->format('d/m/Y'),
            default => 'sem período',
        };
    }
}
