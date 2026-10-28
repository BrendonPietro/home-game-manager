<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Jogos\JogoResource;
use App\Models\Jogo;
use App\Services\Painel\Indicadores;
use App\Support\EtapaDoJogo;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Widgets\Widget;

/**
 * Topo do dashboard (tela 16): próximo jogo e números do clube; para quem tem `financeiro.ver`,
 * arrecadação, prize pools e aluguéis do mês e os valores pendentes (com a lista num modal).
 */
class VisaoGeral extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.painel.widgets.visao-geral';

    // Em array: o Filament aplica um valor solto só a partir do breakpoint lg.
    protected int|string|array $columnSpan = ['default' => 'full'];

    protected static bool $isLazy = false;

    public static function podeVerFinanceiro(): bool
    {
        return Filament::auth()->user()?->can('financeiro.ver') ?? false;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $indicadores = app(Indicadores::class);
        $mes = today();
        $conta = Filament::auth()->user();
        $proximo = $conta->can('viewAny', Jogo::class) ? $indicadores->proximoJogo() : null;

        return [
            'mes' => $mes,
            'proximo' => $proximo,
            'podeEditarProximo' => $proximo && $conta->can('update', $proximo),
            'podeVerJogos' => $conta->can('viewAny', Jogo::class),
            'podeCriarJogo' => $conta->can('create', Jogo::class),
            'jogadores' => $indicadores->jogadores(),
            'jogos' => $indicadores->jogosNoMes($mes),
            'mesas' => $indicadores->mesas(),
            'financeiro' => self::podeVerFinanceiro() ? $indicadores->financeiroDoMes($mes) : null,
            'pendencias' => self::podeVerFinanceiro() ? $indicadores->pendencias() : null,
        ];
    }

    /** URL do botão do próximo jogo: editar no painel (ou ver, se não pode editar). */
    public function urlDoJogo(Jogo $jogo): string
    {
        return Filament::auth()->user()->can('update', $jogo)
            ? JogoResource::getUrl('edit', ['record' => $jogo])
            : JogoResource::getUrl('view', ['record' => $jogo]);
    }

    public function urlNoSite(Jogo $jogo): ?string
    {
        return EtapaDoJogo::url($jogo);
    }

    public function novoJogoUrl(): string
    {
        return JogoResource::getUrl('create');
    }

    public function pendenciasAction(): Action
    {
        return Action::make('pendencias')
            ->label('ver')
            ->link()
            ->size('sm')
            ->visible(fn (): bool => self::podeVerFinanceiro())
            ->modalHeading('Valores pendentes')
            ->modalDescription('Saldos em aberto dos jogos finalizados e aluguéis que já passaram da data sem pagar.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalContent(fn () => view('filament.painel.widgets.pendencias', ['pendencias' => app(Indicadores::class)->pendencias()]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar');
    }
}
