<?php

namespace App\Filament\Resources\Modelos\Pages;

use App\Filament\Resources\Jogos\Pages\Concerns\ConfereAPremiacao;
use App\Filament\Resources\Modelos\ModeloResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModelo extends EditRecord
{
    use ConfereAPremiacao;

    protected static string $resource = ModeloResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ModeloResource::acaoCriarJogo(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ['nome' => $data['nome_modelo']] + $data;
    }
}
