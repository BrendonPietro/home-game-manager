<?php

namespace App\Filament\Resources\Grupos\Pages;

use App\Filament\Resources\Grupos\GrupoResource;
use App\Models\Grupo;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListGrupos extends ListRecords
{
    protected static string $resource = GrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $abas = ['todos' => Tab::make('Todos')];

        foreach (Grupo::GUARDS as $guard => $rotulo) {
            $abas[$guard] = Tab::make($rotulo)->modifyQueryUsing(fn (Builder $query) => $query->where('guard_name', $guard));
        }

        return $abas;
    }
}
