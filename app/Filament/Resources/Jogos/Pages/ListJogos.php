<?php

namespace App\Filament\Resources\Jogos\Pages;

use App\Filament\Resources\Jogos\JogoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJogos extends ListRecords
{
    protected static string $resource = JogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
