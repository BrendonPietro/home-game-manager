<?php

namespace App\Filament\Resources\Usuarios\Schemas;

use App\Models\Usuario;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class UsuarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Dados')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('foto')
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('usuarios')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                        TextInput::make('nome')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('apelido')
                            ->maxLength(60)
                            ->helperText('Aparece na mesa no lugar do nome.'),
                        TextInput::make('telefone')
                            ->tel()
                            ->maxLength(20)
                            ->placeholder('(11) 91234-5678'),
                    ]),

                Section::make('Na noite')
                    ->columns(3)
                    ->schema([
                        Toggle::make('pode_ser_dealer')
                            ->label('Pode ser dealer'),
                        Toggle::make('pode_ser_floor')
                            ->label('Pode ser floor'),
                        Toggle::make('ativo')
                            ->default(true)
                            ->helperText('Inativo não aparece para confirmar jogadores.'),
                    ]),

                // Dar acesso ao site é coisa de quem edita usuários (o grupo Geral só cadastra jogadores).
                Section::make('Acesso ao site')
                    ->description('Quem opera a noite entra no site com e-mail e senha.')
                    ->visible(fn (): bool => (bool) Filament::auth()->user()?->can('usuarios.editar'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('acessa_site')
                            ->label('Acessa o site')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->required(fn (Get $get): bool => (bool) $get('acessa_site'))
                            // O login do site compara em minúsculas: valida e grava já limpo.
                            ->mutateStateForValidationUsing(fn (?string $state): ?string => self::email($state))
                            ->dehydrateStateUsing(fn (?string $state): ?string => self::email($state))
                            ->visible(fn (Get $get): bool => (bool) $get('acessa_site')),
                        TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->required(fn (Get $get, ?Usuario $record): bool => $get('acessa_site') && blank($record?->password))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (?Usuario $record): ?string => filled($record?->password) ? 'Em branco mantém a senha atual.' : null)
                            ->visible(fn (Get $get): bool => (bool) $get('acessa_site')),
                        Select::make('roles')
                            ->label('Grupos do site')
                            ->relationship('roles', 'name', fn (Builder $query) => $query->where('guard_name', 'web'))
                            ->multiple()
                            ->preload()
                            ->required(fn (Get $get): bool => (bool) $get('acessa_site'))
                            ->helperText('Operador lança entradas, pagamentos e eliminações; Admin também altera o jogo.')
                            ->visible(fn (Get $get): bool => (bool) $get('acessa_site'))
                            ->columnSpanFull(),
                    ]),

                Textarea::make('observacoes')
                    ->label('Observações')
                    ->rows(3),
            ]);
    }

    private static function email(?string $email): ?string
    {
        return filled($email) ? Str::lower(trim($email)) : null;
    }
}
