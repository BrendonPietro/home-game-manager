<?php

namespace App\Filament\Resources\Jogos\Pages;

use App\Filament\Resources\Jogos\JogoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewJogo extends ViewRecord
{
    protected static string $resource = JogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
