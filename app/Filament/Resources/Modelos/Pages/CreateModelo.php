<?php

namespace App\Filament\Resources\Modelos\Pages;

use App\Filament\Resources\Modelos\ModeloResource;
use Filament\Resources\Pages\CreateRecord;

class CreateModelo extends CreateRecord
{
    protected static string $resource = ModeloResource::class;

    /** Modelo não tem data; o nome do jogo acompanha o do modelo (cada jogo criado ganha o seu). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ['e_modelo' => true, 'nome' => $data['nome_modelo'], 'data' => null] + $data;
    }
}
