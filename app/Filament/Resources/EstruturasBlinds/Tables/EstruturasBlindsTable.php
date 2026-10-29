<?php

namespace App\Filament\Resources\EstruturasBlinds\Tables;

use App\Models\EstruturaBlinds;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
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
                // Cópia com os mesmos níveis, para montar uma variação ("Turbo 12 min").
                ReplicateAction::make()
                    ->label('Duplicar')
                    ->icon('rh-copy')
                    ->excludeAttributes(['jogos_count'])
                    ->beforeReplicaSaved(fn (EstruturaBlinds $replica) => $replica->nome = $replica->nome.' (cópia)')
                    ->after(function (EstruturaBlinds $replica, EstruturaBlinds $record): void {
                        foreach ($record->niveis as $nivel) {
                            $replica->niveis()->create($nivel->only([
                                'ordem', 'small_blind', 'big_blind', 'ante', 'duracao_minutos', 'e_intervalo', 'nome_intervalo',
                            ]));
                        }
                    })
                    ->successRedirectUrl(fn (EstruturaBlinds $replica): string => route('filament.painel.resources.estruturas.edit', $replica)),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
