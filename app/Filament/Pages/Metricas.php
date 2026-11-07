<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ArrecadacaoPorJogo;
use App\Filament\Widgets\JogadoresPorJogo;
use App\Filament\Widgets\JogosPorMes;
use App\Filament\Widgets\PrizePoolPorJogo;
use App\Services\Relatorios\Filtro;
use App\Services\Relatorios\Metricas as GeradorDeMetricas;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Dashboards e relatórios → Métricas: as médias dos jogos finalizados no período (jogadores, prize pool,
 * arrecadação, quanto cada jogador gasta, rebuys e add-ons) e a comparação por tipo de evento, por
 * modalidade e pelo valor do buy-in, com a evolução mês a mês (Services\Relatorios\Metricas). No fim, os
 * gráficos dos últimos jogos (arrecadação, prize pool, jogadores e jogos por mês), que não seguem o filtro.
 */
class Metricas extends Page
{
    protected string $view = 'filament.painel.metricas';

    protected static string|BackedEnum|null $navigationIcon = 'rh-chart';

    protected static string|UnitEnum|null $navigationGroup = 'Dashboards e relatórios';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Métricas';

    protected static ?string $title = 'Métricas';

    protected static ?string $slug = 'metricas';

    /** @var array<string, mixed> */
    public ?array $filtros = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('relatorios.ver') ?? false;
    }

    public function mount(): void
    {
        $this->filtrosForm->fill(['periodo' => 'tudo']);
    }

    public function filtrosForm(Schema $schema): Schema
    {
        $personalizado = fn (Get $get): bool => $get('periodo') === 'personalizado';

        return $schema
            ->statePath('filtros')
            ->columns(['md' => 3])
            ->components([
                Select::make('periodo')
                    ->label('Período')
                    ->options(fn (): array => Filtro::opcoesDePeriodo())
                    ->selectablePlaceholder(false)
                    ->live(),
                DatePicker::make('de')
                    ->label('De')
                    ->visible($personalizado)
                    ->live(),
                DatePicker::make('ate')
                    ->label('Até')
                    ->afterOrEqual('de')
                    ->visible($personalizado)
                    ->live(),
            ]);
    }

    protected function getFooterWidgets(): array
    {
        return [ArrecadacaoPorJogo::class, PrizePoolPorJogo::class, JogadoresPorJogo::class, JogosPorMes::class];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return ['md' => 2];
    }

    /** @return array<string, mixed> */
    public function metricas(): array
    {
        return app(GeradorDeMetricas::class)->gerar(Filtro::doFormulario($this->filtros ?? []));
    }
}
