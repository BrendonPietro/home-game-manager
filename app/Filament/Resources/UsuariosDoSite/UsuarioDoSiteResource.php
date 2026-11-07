<?php

namespace App\Filament\Resources\UsuariosDoSite;

use App\Filament\Resources\UsuariosDoSite\Pages\ManageUsuariosDoSite;
use App\Models\Grupo;
use App\Models\Usuario;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Sistema → Usuários do site: quem entra no site de operação (e-mail, senha e grupos do site). É a mesma
 * tabela `usuarios` dos Jogadores, só com quem tem `acessa_site`; aqui se dá e se tira o acesso, sem
 * apagar a pessoa (que pode jogar). Permissão própria do painel: `acessos.*` (o Geral não tem).
 */
class UsuarioDoSiteResource extends Resource
{
    protected static ?string $model = Usuario::class;

    protected static ?string $slug = 'usuarios-do-site';

    protected static string|BackedEnum|null $navigationIcon = 'rh-key';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Usuários do site';

    protected static ?string $modelLabel = 'usuário do site';

    protected static ?string $pluralModelLabel = 'usuários do site';

    protected static ?string $recordTitleAttribute = 'nome';

    protected static bool $isGloballySearchable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('acessa_site', true);
    }

    /** Pela permissão `acessos.*`, não pela policy dos Jogadores (`usuarios.*`). */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $acao = $action instanceof BackedEnum ? $action->value : ($action instanceof UnitEnum ? $action->name : $action);
        $permissao = match ($acao) {
            'viewAny', 'view' => 'ver',
            'create' => 'criar',
            'update' => 'editar',
            'delete', 'deleteAny' => 'excluir',
            default => null,
        };
        $conta = Filament::auth()->user();

        return $permissao && $conta?->ativo && $conta->can("acessos.{$permissao}") ? Response::allow() : Response::deny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                self::campoEmail(),
                self::campoSenha(),
                Toggle::make('ativo')
                    ->default(true)
                    ->inline(false)
                    ->helperText('Inativo não entra no site (e sai na hora, se estiver dentro).'),
                self::campoGrupos(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->emptyStateHeading('Ninguém entra no site ainda')
            ->emptyStateDescription('Dê acesso a um jogador já cadastrado ou crie um usuário novo.')
            ->columns([
                TextColumn::make('nome')
                    ->description(fn (Usuario $usuario): ?string => $usuario->apelido)
                    ->searchable(['nome', 'apelido', 'email'])
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Grupos do site')
                    ->badge()
                    ->placeholder('Sem grupo'),
                IconColumn::make('ativo')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('ativo'),
            ])
            ->recordActions([
                EditAction::make(),
                self::acaoTirarAcesso(),
            ]);
    }

    /** Header da lista: um jogador que já está no cadastro passa a entrar no site. */
    public static function acaoDarAcesso(): Action
    {
        return Action::make('darAcesso')
            ->label('Dar acesso a um jogador')
            ->icon('rh-users')
            ->color('gray')
            ->visible(fn (): bool => static::canCreate())
            ->modalHeading('Dar acesso ao site')
            ->modalDescription('O jogador continua no cadastro de Jogadores; só passa a entrar no site.')
            ->modalSubmitActionLabel('Dar acesso')
            ->schema([
                Select::make('usuario')
                    ->label('Jogador')
                    ->required()
                    ->searchable()
                    ->options(fn (): array => Usuario::query()->where('acessa_site', false)->orderBy('nome')->get()
                        ->mapWithKeys(fn (Usuario $u): array => [$u->id => $u->apelido ? "{$u->nome} ({$u->apelido})" : $u->nome])
                        ->all()),
                self::campoEmail(),
                self::campoSenha(),
                // Sem registro no modal: as opções vêm direto (o relationship é só do formulário).
                Select::make('roles')
                    ->label('Grupos do site')
                    ->options(fn (): array => Grupo::query()->where('guard_name', 'web')->orderBy('name')->pluck('name', 'id')->all())
                    ->multiple()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $usuario = Usuario::query()->where('acessa_site', false)->findOrFail($data['usuario']);
                $usuario->forceFill([
                    'acessa_site' => true,
                    'email' => $data['email'],
                    'password' => $data['password'],
                ])->save();
                $usuario->syncRoles(Grupo::query()->where('guard_name', 'web')->whereKey($data['roles'])->get());

                Notification::make()->success()->title("{$usuario->nome} já pode entrar no site.")->send();
            });
    }

    /** Tira só o acesso: a pessoa continua (pode jogar); sai do site na hora (AcessoDoSite). */
    private static function acaoTirarAcesso(): Action
    {
        return Action::make('tirarAcesso')
            ->label('Tirar acesso')
            ->icon('rh-x')
            ->color('danger')
            ->visible(fn (Usuario $record): bool => static::canEdit($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Usuario $record): string => 'Tirar o acesso de '.$record->nome)
            ->modalDescription('Deixa de entrar no site (sai na hora, se estiver dentro). Continua no cadastro de Jogadores.')
            ->modalSubmitActionLabel('Tirar acesso')
            ->action(function (Usuario $record): void {
                $record->forceFill(['acessa_site' => false, 'remember_token' => Str::random(60)])->save();
                $record->syncRoles([]);

                Notification::make()->success()->title("{$record->nome} não entra mais no site.")->send();
            });
    }

    private static function campoEmail(): TextInput
    {
        return TextInput::make('email')
            ->label('E-mail')
            ->email()
            ->required()
            ->maxLength(255)
            ->unique(Usuario::class, 'email', ignoreRecord: true)
            // O login do site compara em minúsculas: valida e grava já limpo.
            ->mutateStateForValidationUsing(fn (?string $state): ?string => self::email($state))
            ->dehydrateStateUsing(fn (?string $state): ?string => self::email($state));
    }

    private static function campoSenha(): TextInput
    {
        return TextInput::make('password')
            ->label('Senha')
            ->password()
            ->revealable()
            ->minLength(8)
            ->required(fn (?Usuario $record): bool => blank($record?->password))
            ->dehydrated(fn (?string $state): bool => filled($state))
            ->helperText(fn (?Usuario $record): string => filled($record?->password)
                ? 'Em branco mantém a senha atual.'
                : 'Senha inicial: a pessoa troca em Minha conta, no site.');
    }

    private static function campoGrupos(): Select
    {
        return Select::make('roles')
            ->label('Grupos do site')
            ->relationship('roles', 'name', fn (Builder $query) => $query->where('guard_name', 'web'))
            ->multiple()
            ->preload()
            ->required()
            ->helperText('Operador lança entradas, pagamentos e eliminações; Admin também altera o jogo; Desenvolvedor faz tudo.')
            ->columnSpanFull();
    }

    private static function email(?string $email): ?string
    {
        return filled($email) ? Str::lower(trim($email)) : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsuariosDoSite::route('/'),
        ];
    }
}
