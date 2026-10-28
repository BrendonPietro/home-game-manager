<?php

namespace App\Filament\Resources\Rankings\Tables;

use App\Enums\Periodicidade;
use App\Filament\Resources\Rankings\RankingResource;
use App\Models\Ranking;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RankingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->modifyQueryUsing(fn ($query) => $query->withCount('jogos')->with('pontuacoes'))
            ->recordUrl(fn (Ranking $ranking): string => RankingResource::getUrl('classificacao', ['record' => $ranking]))
            ->columns([
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('periodicidade')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (Periodicidade $state): string => $state->label()),
                TextColumn::make('periodo')
                    ->label('Período')
                    ->state(fn (Ranking $r): string => ($r->inicio?->format('d/m/Y') ?? 'desde sempre').' → '.($r->fim?->format('d/m/Y') ?? 'sem fim')),
                TextColumn::make('regra')
                    ->label('Pontos')
                    ->state(fn (Ranking $r): string => $r->pontuacoes->pluck('pontos')->join(' / ') ?: '—')
                    ->description(fn (Ranking $r): ?string => collect([
                        $r->ft_pontua ? "mesa final +{$r->pontos_mesa_final}" : null,
                        $r->participacao_pontua ? "participação +{$r->pontos_participacao}" : null,
                    ])->filter()->join(' · ') ?: null),
                TextColumn::make('jogos_count')->label('Jogos')->numeric(),
                IconColumn::make('ativo')->boolean(),
            ])
            ->recordActions([
                Action::make('classificacao')
                    ->label('Classificação')
                    ->icon('rh-list')
                    ->url(fn (Ranking $r): string => RankingResource::getUrl('classificacao', ['record' => $r])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
