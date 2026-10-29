<?php

namespace App\Filament\Resources\EstruturasBlinds\Pages;

use App\Filament\Resources\EstruturasBlinds\EstruturaBlindsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEstruturasBlinds extends ListRecords
{
    protected static string $resource = EstruturaBlindsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
