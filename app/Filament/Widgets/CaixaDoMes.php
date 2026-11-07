<?php

namespace App\Filament\Widgets;

use App\Services\Painel\Indicadores;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Widgets\Widget;

/**
 * Painel de controle, só com `financeiro.ver`: arrecadação, prize pools e aluguéis do mês e os valores
 * pendentes (com a lista num modal).
 */
class CaixaDoMes extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.painel.widgets.caixa-do-mes';

    // Em array: o Filament aplica um valor solto só a partir do breakpoint lg.
    protected int|string|array $columnSpan = ['default' => 'full'];

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can('financeiro.ver') ?? false;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $indicadores = app(Indicadores::class);

        return [
            'mes' => today(),
            'financeiro' => $indicadores->financeiroDoMes(today()),
            'pendencias' => $indicadores->pendencias(),
        ];
    }

    public function pendenciasAction(): Action
    {
        return Action::make('pendencias')
            ->label('ver')
            ->link()
            ->size('sm')
            ->visible(fn (): bool => static::canView())
            ->modalHeading('Valores pendentes')
            ->modalDescription('Saldos em aberto dos jogos finalizados e aluguéis que já passaram da data sem pagar.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalContent(fn () => view('filament.painel.widgets.pendencias', ['pendencias' => app(Indicadores::class)->pendencias()]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar');
    }
}
