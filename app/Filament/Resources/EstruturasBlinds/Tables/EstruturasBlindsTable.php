<?php

namespace App\Filament\Resources\EstruturasBlinds\Tables;

use App\Filament\Resources\EstruturasBlinds\EstruturaBlindsResource;
use App\Models\EstruturaBlinds;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EstruturasBlindsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->modifyQueryUsing(fn ($query) => $query->withCount('jogos')->with('niveis'))
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable()
                    ->description(fn (EstruturaBlinds $e): ?string => $e->descricao),
                TextColumn::make('resumo')
                    ->label('Níveis')
                    ->state(fn (EstruturaBlinds $e): string => $e->resumo()),
                TextColumn::make('jogos_count')
                    ->label('Jogos')
                    ->numeric()
                    ->tooltip('Jogos e modelos que usam a estrutura'),
                IconColumn::make('ativo')->boolean(),
            ])
            ->recordActions([
                EstruturaBlindsResource::duplicar(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
