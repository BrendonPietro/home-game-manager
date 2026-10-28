<?php

namespace App\Filament\Resources\Rankings\Pages;

use App\Filament\Resources\Rankings\RankingResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRanking extends EditRecord
{
    protected static string $resource = RankingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('classificacao')
                ->label('Classificação')
                ->icon('rh-list')
                ->color('gray')
                ->url(fn (): string => RankingResource::getUrl('classificacao', ['record' => $this->record])),
            DeleteAction::make(),
        ];
    }
}
