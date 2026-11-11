<?php

namespace App\Filament\Resources\Grupos;

use App\Filament\Resources\Grupos\Pages\CreateGrupo;
use App\Filament\Resources\Grupos\Pages\EditGrupo;
use App\Filament\Resources\Grupos\Pages\ListGrupos;
use App\Models\Grupo;
use App\Support\Permissoes;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Grupos de permissões do painel e do site, com a grade de permissões (no painel, uma linha por
 * recurso: ver, criar, editar, excluir). Quem entra no grupo se escolhe na conta (Segurança) ou no
 * usuário (Usuários). As permissões gravam pelo syncPermissions do spatie (limpa o cache).
 */
class GrupoResource extends Resource
{
    protected static ?string $model = Grupo::class;

    protected static string|BackedEnum|null $navigationIcon = 'rh-menu-grupos';

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'grupo';

    protected static ?string $pluralModelLabel = 'grupos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(60)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('guard_name', $get('guard_name'))),
                ToggleButtons::make('guard_name')
                    ->label('Lado')
                    ->options(Grupo::GUARDS)
                    ->default('painel')
                    ->inline()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('permissoes', []))
                    ->disabledOn('edit')
                    ->helperText('Painel: contas do /painel. Site: quem opera a noite.'),
                Section::make('Permissões')
                    ->description(fn (Get $get): string => $get('guard_name') === 'web'
                        ? 'O que quem está no grupo pode fazer no site durante a noite.'
                        : 'Cada linha é um cadastro do painel: ver, criar, editar e excluir.')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('permissoes')
                            ->hiddenLabel()
                            ->options(fn (Get $get): array => Permissoes::opcoes($get('guard_name') ?: 'painel'))
                            ->columns(fn (Get $get): array => $get('guard_name') === 'web' ? ['md' => 2, 'xl' => 3] : ['md' => 2, 'xl' => 4])
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->searchable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn ($query) => $query->withCount('permissions'))
            ->columns([
                TextColumn::make('name')
                    ->label('Grupo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->label('Lado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Grupo::GUARDS[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'web' ? 'info' : 'gray'),
                TextColumn::make('permissions_count')
                    ->label('Permissões')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('membros')
                    ->label('Pessoas')
                    ->state(fn (Grupo $grupo): int => $grupo->membros())
                    ->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrupos::route('/'),
            'create' => CreateGrupo::route('/create'),
            'edit' => EditGrupo::route('/{record}/edit'),
        ];
    }
}
