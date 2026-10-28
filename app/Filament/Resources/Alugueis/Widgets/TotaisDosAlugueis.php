<?php

namespace App\Filament\Resources\Alugueis\Widgets;

use App\Enums\StatusAluguel;
use App\Filament\Resources\Alugueis\AluguelResource;
use App\Filament\Resources\Alugueis\Pages\ListAlugueis;
use App\Models\Aluguel;
use App\Support\Dinheiro;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Totais da tela de aluguéis por status (reservado, confirmado, pago, cancelado). Segue o filtro de
 * período da tabela, mas não a aba: as quatro somas aparecem sempre.
 */
class TotaisDosAlugueis extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getTablePage(): string
    {
        return ListAlugueis::class;
    }

    protected function getStats(): array
    {
        $totais = AluguelResource::noPeriodo(Aluguel::query(), $this->tableFilters['periodo'] ?? [])
            ->get(['status', 'valor'])
            ->groupBy(fn (Aluguel $aluguel): string => $aluguel->status->value);

        return array_map(function (StatusAluguel $status) use ($totais): Stat {
            $alugueis = $totais->get($status->value, collect());
            $quantos = $alugueis->count();

            return Stat::make($status->label(), Dinheiro::brlCentavos($alugueis->sum(fn (Aluguel $a): int => Dinheiro::centavos($a->valor))))
                ->description($quantos === 1 ? '1 aluguel' : "{$quantos} aluguéis")
                ->color(AluguelResource::corDoStatus($status));
        }, StatusAluguel::cases());
    }
}
