<?php

namespace App\Filament\Resources\Jogos\Tables;

use App\Enums\Modalidade;
use App\Enums\StatusJogo;
use App\Filament\Resources\Jogos\JogoResource;
use App\Filament\Resources\Jogos\Schemas\JogoForm;
use App\Models\Jogo;
use App\Support\Dinheiro;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JogosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data', 'desc')
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data')
                    ->date('d/m/Y')
                    ->description(fn (Jogo $jogo): ?string => $jogo->horario ? substr($jogo->horario, 0, 5) : null)
                    ->sortable(),
                TextColumn::make('modalidade')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (Modalidade $state): string => $state->label()),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (StatusJogo $state): string => $state->label())
                    ->color(fn (StatusJogo $state): string => JogoForm::corDoStatus($state)),
                TextColumn::make('participantes_count')
                    ->label('Jogadores')
                    ->counts('participantes')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('entradas_sum_valor')
                    ->label('Arrecadação')
                    ->sum(['entradas' => fn (Builder $query) => $query->whereNull('cancelada_em')], 'valor')
                    ->formatStateUsing(fn (?string $state): string => Dinheiro::brl($state))
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(StatusJogo::opcoes()),
                SelectFilter::make('modalidade')->options(Modalidade::opcoes()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                JogoResource::acaoExcluir(),
            ]);
    }
}
