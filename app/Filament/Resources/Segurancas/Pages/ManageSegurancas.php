<?php

namespace App\Filament\Resources\Segurancas\Pages;

use App\Filament\Resources\Segurancas\SegurancaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSegurancas extends ManageRecords
{
    protected static string $resource = SegurancaResource::class;

    protected static ?string $title = 'Contas do painel';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
