<?php

namespace App\Filament\Resources\Temporadas\Pages;

use App\Filament\Resources\Temporadas\TemporadaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTemporadas extends ManageRecords
{
    protected static string $resource = TemporadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
