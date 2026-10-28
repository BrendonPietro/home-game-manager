<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Jogos\JogoResource;
use App\Filament\Widgets\ArrecadacaoPorJogo;
use App\Filament\Widgets\JogadoresPorJogo;
use App\Filament\Widgets\JogosPorMes;
use App\Filament\Widgets\PrizePoolPorJogo;
use App\Filament\Widgets\Proximos;
use App\Filament\Widgets\VisaoGeral;
use App\Models\Jogo;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Str;

/**
 * Tela 16: números do mês, próximo jogo, valores pendentes, gráficos dos últimos jogos e a agenda.
 * Grade de 3 colunas: dois gráficos por linha e os Próximos à direita, ocupando duas linhas.
 */
class Dashboard extends BaseDashboard
{
    public function getSubheading(): ?string
    {
        return Str::ucfirst(today()->translatedFormat('F Y'));
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 3];
    }

    public function getWidgets(): array
    {
        return [
            VisaoGeral::class,
            ArrecadacaoPorJogo::class,
            PrizePoolPorJogo::class,
            Proximos::class,
            JogadoresPorJogo::class,
            JogosPorMes::class,
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
