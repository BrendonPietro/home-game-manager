<?php

namespace App\Filament\Resources\Jogos\Pages;

use App\Filament\Resources\Jogos\JogoResource;
use App\Services\Jogo\EventoDoJogo;
use Filament\Resources\Pages\CreateRecord;

class CreateJogo extends CreateRecord
{
    protected static string $resource = JogoResource::class;

    protected function afterCreate(): void
    {
        app(EventoDoJogo::class)->sincronizar($this->record);
    }
}
