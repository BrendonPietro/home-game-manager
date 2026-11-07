<?php

namespace App\Filament\Resources\Jogos\RelationManagers;

use App\Enums\StatusJogo;
use App\Enums\TipoAlteracao;
use App\Models\Alteracao;
use App\Models\Jogo;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Histórico do jogo (aba Histórico): quem mudou o quê e quando, pelo site ou pelo painel, com o motivo
 * das correções (voltar fase, desfazer). Só leitura: as linhas nascem nas Actions (Services\Jogo\Historico).
 */
class HistoricoRelationManager extends RelationManager
{
    protected static string $relationship = 'alteracoes';

    protected static ?string $title = 'Histórico';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Jogo
            && ! $ownerRecord->e_modelo
            && $ownerRecord->status !== StatusJogo::Rascunho
            && (bool) Filament::auth()->user()?->can('jogos.ver');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['usuario', 'conta']))
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Nada registrado ainda')
            ->emptyStateDescription('Início, fases, correções, acertos e o fim do jogo aparecem aqui, com quem fez.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('tipo')
                    ->label('O quê')
                    ->formatStateUsing(fn (TipoAlteracao $state): string => $state->label())
                    ->badge()
                    ->color(fn (TipoAlteracao $state): string => $state->correcao() ? 'warning' : 'gray'),
                TextColumn::make('descricao')
                    ->label('Detalhe')
                    ->wrap(),
                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('quem')
                    ->label('Quem')
                    ->state(fn (Alteracao $alteracao): string => $alteracao->quem()),
            ])
            ->filters([
                SelectFilter::make('tipo')
                    ->label('O quê')
                    ->options(TipoAlteracao::opcoes()),
            ]);
    }
}
