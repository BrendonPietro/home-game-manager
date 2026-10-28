<?php

namespace App\Filament\Resources\Usuarios\Tables;

use App\Models\Usuario;
use App\Support\Dinheiro;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
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
                TextColumn::make('funcoes')
                    ->label('Na noite')
                    ->badge()
                    ->state(fn (Usuario $usuario): array => array_keys(array_filter([
                        'Dealer' => $usuario->pode_ser_dealer,
                        'Floor' => $usuario->pode_ser_floor,
                    ])))
                    ->placeholder('—'),
                TextColumn::make('acesso')
                    ->label('Acesso ao site')
                    ->badge()
                    ->color('info')
                    ->state(fn (Usuario $usuario): array => $usuario->acessa_site
                        ? ($usuario->roles->pluck('name')->all() ?: ['Sem grupo'])
                        : [])
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
                IconColumn::make('ativo')
                    ->boolean(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('roles')->comSaldoEmAberto())
            ->filters([
                TernaryFilter::make('ativo'),
                TernaryFilter::make('pode_ser_dealer')->label('Dealer'),
                TernaryFilter::make('pode_ser_floor')->label('Floor'),
                TernaryFilter::make('acessa_site')->label('Acessa o site'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }
}
