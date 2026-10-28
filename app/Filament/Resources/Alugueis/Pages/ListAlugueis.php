<?php

namespace App\Filament\Resources\Alugueis\Pages;

use App\Enums\StatusAluguel;
use App\Filament\Resources\Alugueis\AluguelResource;
use App\Filament\Resources\Alugueis\Widgets\TotaisDosAlugueis;
use App\Models\Aluguel;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAlugueis extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = AluguelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AluguelResource::acaoNovo(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TotaisDosAlugueis::class,
        ];
    }

    public function getTabs(): array
    {
        $abas = ['todos' => Tab::make('Todos')];

        foreach (StatusAluguel::cases() as $status) {
            $abas[$status->value] = Tab::make($status->label())
                ->badge(fn (): ?int => Aluguel::query()->where('status', $status)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        return $abas;
    }
}
