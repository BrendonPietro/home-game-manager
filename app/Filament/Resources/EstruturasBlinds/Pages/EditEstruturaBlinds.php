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
            EstruturaBlindsResource::duplicar()
                ->requiresConfirmation()
                ->modalDescription('Faz uma cópia com os níveis já salvos e abre a cópia para editar. O que não foi salvo aqui não vai junto.'),
            DeleteAction::make(),
        ];
    }
}
