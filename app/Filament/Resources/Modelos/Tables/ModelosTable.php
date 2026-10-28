<?php

namespace App\Filament\Resources\Modelos\Tables;

use App\Enums\Modalidade;
use App\Filament\Resources\Modelos\ModeloResource;
use App\Models\Jogo;
use App\Support\Dinheiro;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModelosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nome_modelo')
            ->modifyQueryUsing(fn ($query) => $query->with('itens')->withCount(['taxas', 'rankings', 'mesas']))
            ->columns([
                TextColumn::make('nome_modelo')
                    ->label('Modelo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('modalidade')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (Modalidade $state): string => $state->label()),
                TextColumn::make('buyin')
                    ->label('Buy-in')
                    ->state(fn (Jogo $jogo): string => ($buyin = $jogo->buyinPadrao()) ? Dinheiro::brl($buyin->valor) : '—'),
                TextColumn::make('horario')
                    ->label('Horário')
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 5) : '—'),
                TextColumn::make('mesas_count')->label('Mesas')->numeric(),
                TextColumn::make('rankings_count')->label('Rankings')->numeric(),
            ])
            ->recordActions([
                ModeloResource::acaoCriarJogo(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
