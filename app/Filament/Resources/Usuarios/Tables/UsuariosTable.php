<?php

namespace App\Filament\Resources\Usuarios\Tables;

use App\Models\Usuario;
use App\Support\Dinheiro;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsuariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                ImageColumn::make('foto')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->imageSize(32),
                TextColumn::make('nome')
                    ->description(fn (Usuario $usuario): ?string => $usuario->apelido)
                    ->searchable(['nome', 'apelido'])
                    ->sortable(),
                TextColumn::make('telefone')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('dealer')
                    ->label('Dealer')
                    ->state(fn (Usuario $usuario): ?string => $usuario->pode_ser_dealer ? 'Sim' : null)
                    ->placeholder('—'),
                TextColumn::make('floor')
                    ->label('Floor')
                    ->state(fn (Usuario $usuario): ?string => $usuario->pode_ser_floor ? 'Sim' : null)
                    ->placeholder('—'),
                TextColumn::make('participacoes_count')
                    ->label('Jogos')
                    ->counts('participacoes')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pontos_ranking_sum_pontos')
                    ->label('Pontos')
                    ->sum('pontosRanking', 'pontos')
                    ->numeric()
                    ->placeholder('0')
                    ->sortable(),
                TextColumn::make('saldo_em_aberto')
                    ->label('Saldo em aberto')
                    ->tooltip('Jogos finalizados. Positivo: deve ao caixa; negativo: o caixa deve.')
                    ->formatStateUsing(fn (?string $state): string => Dinheiro::centavos($state) === 0 ? '—' : Dinheiro::brl($state, true))
                    ->color(fn (?string $state): ?string => match (true) {
                        Dinheiro::centavos($state) > 0 => 'warning',
                        Dinheiro::centavos($state) < 0 => 'info',
                        default => null,
                    })
                    ->alignEnd()
                    ->sortable()
                    ->visible(fn (): bool => (bool) Filament::auth()->user()?->can('financeiro.ver')),
            ])
            ->modifyQueryUsing(fn ($query) => $query->comSaldoEmAberto())
            ->filters([
                TernaryFilter::make('ativo'),
                TernaryFilter::make('pode_ser_dealer')->label('Dealer'),
                TernaryFilter::make('pode_ser_floor')->label('Floor'),
                TrashedFilter::make(),
            ])
            ->recordActionsColumnLabel('Ações')
            ->recordActions([
                // Ativo/inativo num clique: o check verde vira o X vermelho (e volta), sem abrir nada.
                Action::make('ativo')
                    ->label(fn (Usuario $usuario): string => $usuario->ativo ? 'Ativo' : 'Inativo')
                    ->tooltip(fn (Usuario $usuario): string => $usuario->ativo ? 'Ativo: clique para desativar' : 'Inativo: clique para ativar')
                    ->icon(fn (Usuario $usuario): string => $usuario->ativo ? 'rh-check' : 'rh-fechar')
                    ->color(fn (Usuario $usuario): string => $usuario->ativo ? 'success' : 'danger')
                    ->iconButton()
                    ->visible(fn (Usuario $usuario): bool => ! $usuario->trashed() && (Filament::auth()->user()?->can('update', $usuario) ?? false))
                    ->action(fn (Usuario $usuario) => $usuario->update(['ativo' => ! $usuario->ativo])),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }
}
