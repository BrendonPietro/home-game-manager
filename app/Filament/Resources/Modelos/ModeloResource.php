<?php

namespace App\Filament\Resources\Modelos;

use App\Actions\Jogo\CriarDeModelo;
use App\Exceptions\RegraDoJogo;
use App\Filament\Resources\Jogos\JogoResource;
use App\Filament\Resources\Jogos\Schemas\JogoForm;
use App\Filament\Resources\Modelos\Pages\CreateModelo;
use App\Filament\Resources\Modelos\Pages\EditModelo;
use App\Filament\Resources\Modelos\Pages\ListModelos;
use App\Filament\Resources\Modelos\Tables\ModelosTable;
use App\Models\Jogo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Torneios modelo (jogos.e_modelo): o mesmo cadastro do jogo, sem data, só para copiar. Nunca
 * aparecem no site nem no calendário. "Criar jogo" copia tudo e pede nome, data e horário.
 * As permissões são as de modelos.* (o model é o mesmo Jogo, cuja policy usa jogos.*).
 */
class ModeloResource extends Resource
{
    protected static ?string $model = Jogo::class;

    protected static ?string $slug = 'modelos';

    protected static string|BackedEnum|null $navigationIcon = 'rh-copy';

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'modelo';

    protected static ?string $pluralModelLabel = 'modelos';

    protected static ?string $recordTitleAttribute = 'nome_modelo';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->modelos();
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $acao = $action instanceof BackedEnum ? $action->value : ($action instanceof UnitEnum ? $action->name : $action);
        $permissao = match ($acao) {
            'viewAny', 'view' => 'ver',
            'create', 'replicate' => 'criar',
            'update', 'reorder' => 'editar',
            'delete', 'deleteAny', 'restore', 'restoreAny' => 'excluir',
            default => null,
        };
        $conta = Filament::auth()->user();

        return $permissao && $conta?->ativo && $conta->can("modelos.{$permissao}") ? Response::allow() : Response::deny();
    }

    public static function form(Schema $schema): Schema
    {
        return JogoForm::configure($schema, modelo: true);
    }

    public static function table(Table $table): Table
    {
        return ModelosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModelos::route('/'),
            'create' => CreateModelo::route('/create'),
            'edit' => EditModelo::route('/{record}/edit'),
        ];
    }

    /** "Criar jogo": copia o modelo para um jogo em rascunho e abre a edição dele. */
    public static function acaoCriarJogo(): Action
    {
        return Action::make('criarJogo')
            ->label('Criar jogo')
            ->icon('rh-plus')
            ->color('primary')
            ->visible(fn (): bool => (bool) Filament::auth()->user()?->can('jogos.criar'))
            ->modalHeading(fn (Jogo $record): string => "Criar jogo a partir de {$record->nome_modelo}")
            ->modalDescription('Entradas, opcionais, taxas, premiação, rankings e mesas vêm do modelo. O jogo nasce em rascunho.')
            ->modalSubmitActionLabel('Criar jogo')
            ->schema(fn (Jogo $record): array => [
                TextInput::make('nome')->required()->maxLength(255)->placeholder('Poker Night #20'),
                DatePicker::make('data')->required(),
                TimePicker::make('horario')->label('Horário')->seconds(false)->default($record->horario ? substr($record->horario, 0, 5) : '20:00'),
            ])
            ->action(function (Jogo $record, array $data, Action $action) {
                try {
                    $jogo = app(CriarDeModelo::class)($record, $data['nome'], $data['data'], $data['horario'] ?? null);
                } catch (RegraDoJogo $erro) {
                    Notification::make()->danger()->title($erro->getMessage())->send();
                    $action->halt();

                    return;
                }

                Notification::make()->success()->title("{$jogo->nome} criado a partir do modelo.")->send();
                $action->redirect(JogoResource::getUrl('edit', ['record' => $jogo]));
            });
    }
}
