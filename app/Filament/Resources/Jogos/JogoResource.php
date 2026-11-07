<?php

namespace App\Filament\Resources\Jogos;

use App\Filament\Resources\Jogos\Pages\CreateJogo;
use App\Filament\Resources\Jogos\Pages\EditJogo;
use App\Filament\Resources\Jogos\Pages\ListJogos;
use App\Filament\Resources\Jogos\Pages\ViewJogo;
use App\Filament\Resources\Jogos\RelationManagers\ConfirmadosRelationManager;
use App\Filament\Resources\Jogos\RelationManagers\HistoricoRelationManager;
use App\Filament\Resources\Jogos\Schemas\JogoForm;
use App\Filament\Resources\Jogos\Tables\JogosTable;
use App\Filament\Resources\Modelos\ModeloResource;
use App\Models\Jogo;
use App\Services\Jogo\CopiaDoJogo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Telas 19 e 20: jogos de verdade (torneio e cash game). Torneios modelo ficam no ModeloResource.
 * Finalizado e cancelado abrem só para ver (o formulário desabilitado).
 */
class JogoResource extends Resource
{
    protected static ?string $model = Jogo::class;

    protected static string|BackedEnum|null $navigationIcon = 'rh-trophy';

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'jogo';

    protected static ?string $pluralModelLabel = 'jogos';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->reais();
    }

    public static function form(Schema $schema): Schema
    {
        return JogoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JogosTable::configure($table);
    }

    /** Pré-torneio (aba Confirmados: lista e pagamentos antecipados) e o histórico de alterações do jogo. */
    public static function getRelations(): array
    {
        return [
            ConfirmadosRelationManager::class,
            HistoricoRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJogos::route('/'),
            'create' => CreateJogo::route('/create'),
            'view' => ViewJogo::route('/{record}'),
            'edit' => EditJogo::route('/{record}/edit'),
        ];
    }

    /** Novo rascunho com a mesma estrutura (entradas, taxas, premiação, mesas, rankings), sem data. */
    public static function acaoDuplicar(): Action
    {
        return Action::make('duplicar')
            ->label('Duplicar')
            ->icon('rh-copy')
            ->color('gray')
            ->visible(fn (): bool => (bool) Filament::auth()->user()?->can('jogos.criar'))
            ->modalHeading('Duplicar jogo')
            ->modalDescription('Copia entradas, opcionais, taxas, premiação, rankings e mesas para um jogo novo em rascunho. Lista, lugares e caixa não vêm junto.')
            ->modalSubmitActionLabel('Duplicar')
            ->schema(fn (Jogo $record): array => [
                TextInput::make('nome')->required()->maxLength(255)->default($record->nome.' (cópia)'),
            ])
            ->action(function (Jogo $record, array $data, Action $action) {
                $copia = app(CopiaDoJogo::class)->copiar($record, ['nome' => $data['nome']]);

                Notification::make()->success()->title('Jogo duplicado: confira e defina a data.')->send();
                $action->redirect(static::getUrl('edit', ['record' => $copia]));
            });
    }

    /** O cadastro deste jogo vira um torneio modelo (o jogo continua como está). */
    public static function acaoSalvarComoModelo(): Action
    {
        return Action::make('salvarComoModelo')
            ->label('Salvar como modelo')
            ->icon('rh-copy')
            ->color('gray')
            ->visible(fn (): bool => (bool) Filament::auth()->user()?->can('modelos.criar'))
            ->modalHeading('Salvar como modelo')
            ->modalDescription('Entradas, opcionais, taxas, premiação, rankings e mesas viram um modelo para criar os próximos jogos.')
            ->modalSubmitActionLabel('Salvar modelo')
            ->schema([
                TextInput::make('nome_modelo')->label('Nome do modelo')->required()->maxLength(255)->placeholder('Torneio de sábado'),
            ])
            ->action(function (Jogo $record, array $data, Action $action) {
                $modelo = app(CopiaDoJogo::class)->copiar($record, [
                    'nome' => $data['nome_modelo'],
                    'nome_modelo' => $data['nome_modelo'],
                    'e_modelo' => true,
                ]);

                Notification::make()->success()->title("Modelo {$modelo->nome_modelo} salvo.")->send();
                $action->redirect(ModeloResource::getUrl('edit', ['record' => $modelo]));
            });
    }
}
