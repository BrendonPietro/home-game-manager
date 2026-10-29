<?php

namespace App\Filament\Resources\EstruturasBlinds\Pages;

use App\Filament\Resources\EstruturasBlinds\EstruturaBlindsResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEstruturaBlinds extends EditRecord
{
    protected static string $resource = EstruturaBlindsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
