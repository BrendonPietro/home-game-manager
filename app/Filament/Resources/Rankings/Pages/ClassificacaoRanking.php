<?php

namespace App\Filament\Resources\Rankings\Pages;

use App\Filament\Resources\Rankings\RankingResource;
use App\Models\Ranking;
use App\Models\Usuario;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Classificação do ranking: soma dos pontos gravados (ranking_pontos) por jogador, só dos jogos dentro
 * do período do ranking. Desempate por vitórias, melhor posição e jogos.
 *
 * @property Ranking $record
 */
class ClassificacaoRanking extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = RankingResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->autorizar();
    }

    public function hydrate(): void
    {
        $this->autorizar();
    }

    private function autorizar(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->nome;
    }

    public function getSubheading(): ?string
    {
        $ranking = $this->record;

        return 'Classificação · '.($ranking->inicio?->format('d/m/Y') ?? 'desde sempre').' → '.($ranking->fim?->format('d/m/Y') ?? 'sem fim');
    }

    public function getBreadcrumb(): string
    {
        return 'Classificação';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->record($this->record),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->record->classificacao())
            ->defaultSort(fn (Builder $query) => Ranking::ordenarClassificacao($query))
            ->paginated([25, 50, 'all'])
            ->columns([
                TextColumn::make('lugar')->label('#')->rowIndex(),
                TextColumn::make('nome')
                    ->label('Jogador')
                    ->formatStateUsing(fn (Usuario $usuario): string => $usuario->nome_exibicao)
                    ->description(fn (Usuario $usuario): ?string => $usuario->apelido ? $usuario->nome : null)
                    ->searchable(['nome', 'apelido']),
                TextColumn::make('pontos')->numeric()->weight('bold'),
                TextColumn::make('jogos')->numeric(),
                TextColumn::make('vitorias')->label('Vitórias')->numeric(),
                TextColumn::make('melhor')
                    ->label('Melhor posição')
                    ->formatStateUsing(fn (?int $state): string => $state ? $state.'º' : '—'),
            ])
            ->emptyStateHeading('Ninguém pontuou ainda')
            ->emptyStateDescription('Os pontos entram quando um jogo que vale neste ranking é finalizado.');
    }
}
