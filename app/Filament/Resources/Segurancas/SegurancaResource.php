<?php

namespace App\Filament\Resources\Segurancas;

use App\Filament\Resources\Segurancas\Pages\ManageSegurancas;
use App\Models\Grupo;
use App\Models\Permissao;
use App\Models\Seguranca;
use App\Support\Permissoes;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Contas do painel (tabela `seguranca`): login, senha, ativo, grupos e permissões avulsas do painel.
 * Ninguém dá o que não tem: grupos e permissões oferecidos são só os que a conta logada já cobre
 * (SegurancaPolicy impede mexer em conta mais forte e excluir a própria).
 */
class SegurancaResource extends Resource
{
    protected static ?string $model = Seguranca::class;

    protected static string|BackedEnum|null $navigationIcon = 'rh-lock';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationLabel = 'Segurança';

    protected static ?string $modelLabel = 'conta do painel';

    protected static ?string $pluralModelLabel = 'contas do painel';

    protected static ?string $slug = 'seguranca';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('login')
                    ->label('Usuário')
                    ->required()
                    ->maxLength(60)
                    ->regex('/^[A-Za-z0-9._-]+$/')
                    ->validationMessages(['regex' => 'Use só letras, números, ponto, traço e sublinhado.'])
                    ->unique(ignoreRecord: true)
                    ->helperText('Para entrar no painel (sem e-mail).')
                    ->mutateStateForValidationUsing(fn (?string $state): ?string => self::login($state))
                    ->dehydrateStateUsing(fn (?string $state): ?string => self::login($state)),
                TextInput::make('password')
                    ->label('Senha')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (?Seguranca $record): bool => $record === null)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (?Seguranca $record): ?string => $record ? 'Em branco mantém a senha atual.' : null),
                Toggle::make('ativo')
                    ->default(true)
                    ->inline(false)
                    ->helperText('Conta inativa não entra no painel.')
                    // A própria conta não se desativa (ficaria trancada do lado de fora).
                    ->disabled(fn (?Seguranca $record): bool => $record?->is(Filament::auth()->user()) ?? false),
                Select::make('roles')
                    ->label('Grupos')
                    ->relationship('roles', 'name', fn (Builder $query) => $query->where('guard_name', 'painel')
                        ->whereKey(self::gruposQuePodeDar()))
                    ->multiple()
                    ->preload()
                    ->rule(fn (): Closure => function (string $atributo, $valor, Closure $falhar): void {
                        $permissoes = Grupo::query()->whereKey($valor ?? [])->with('permissions')->get()->flatMap->permissions;
                        if (! Permissoes::abrange(Filament::auth()->user(), $permissoes)) {
                            $falhar('Você só pode dar grupos com permissões que você também tem.');
                        }
                    })
                    ->columnSpanFull(),
                Select::make('permissions')
                    ->label('Permissões avulsas')
                    ->relationship('permissions', 'name', fn (Builder $query) => $query->where('guard_name', 'painel')
                        ->whereIn('name', Filament::auth()->user()->getAllPermissions()->pluck('name')))
                    ->getOptionLabelFromRecordUsing(fn (Permissao $permissao): string => Permissoes::rotulo($permissao->name))
                    ->multiple()
                    ->preload()
                    ->helperText('Além das que vêm dos grupos.')
                    ->rule(fn (): Closure => function (string $atributo, $valor, Closure $falhar): void {
                        $permissoes = Permissao::query()->whereKey($valor ?? [])->get();
                        if (! Permissoes::abrange(Filament::auth()->user(), $permissoes)) {
                            $falhar('Você só pode dar permissões que você também tem.');
                        }
                    })
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('login')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Grupos')
                    ->badge()
                    ->placeholder('Sem grupo'),
                IconColumn::make('ativo')
                    ->boolean(),
                TextColumn::make('ultimo_acesso')
                    ->label('Último acesso')
                    ->since()
                    ->dateTimeTooltip('d/m/Y H:i')
                    ->placeholder('Nunca entrou')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('ativo'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /** @return list<int> grupos do painel cujas permissões a conta logada tem */
    private static function gruposQuePodeDar(): array
    {
        $conta = Filament::auth()->user();

        return Grupo::query()->where('guard_name', 'painel')->with('permissions')->get()
            ->filter(fn (Grupo $grupo): bool => Permissoes::abrange($conta, $grupo->permissions))
            ->modelKeys();
    }

    private static function login(?string $login): ?string
    {
        return filled($login) ? Str::lower(trim($login)) : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSegurancas::route('/'),
        ];
    }
}
