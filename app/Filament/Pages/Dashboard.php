<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Jogos\JogoResource;
use App\Filament\Widgets\Atalhos;
use App\Filament\Widgets\CaixaDoMes;
use App\Filament\Widgets\ProximoJogo;
use App\Filament\Widgets\Proximos;
use App\Filament\Widgets\RankingEmDestaque;
use App\Models\Jogo;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Str;

/**
 * Painel de controle (tela 16): a porta de entrada do painel. Em destaque, o próximo jogo e o ranking que vale
 * agora; depois o caixa do mês (só com financeiro.ver) e os atalhos dos módulos ao lado da agenda.
 * Grade de 3 colunas: destaque e atalhos em 2, ranking e agenda em 1. Os gráficos dos últimos jogos ficam nas
 * Métricas (Dashboards e relatórios).
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Painel de controle';

    protected static ?string $title = 'Painel de controle';

    public function getSubheading(): ?string
    {
        return Str::ucfirst(today()->translatedFormat('F Y'));
    }

    public function getColumns(): int|array
    {
        return ['xl' => 3];
    }

    public function getWidgets(): array
    {
        return [
            ProximoJogo::class,
            RankingEmDestaque::class,
            CaixaDoMes::class,
            Atalhos::class,
            Proximos::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('novoJogo')
                ->label('Novo jogo')
                ->icon('rh-plus')
                ->url(fn (): string => JogoResource::getUrl('create'))
                ->visible(fn (): bool => Filament::auth()->user()->can('create', Jogo::class)),
        ];
    }
}
