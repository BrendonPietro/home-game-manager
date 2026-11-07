<?php

namespace App\Filament\Resources\Usuarios\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Cadastros → Jogadores: só quem joga, com os dados, se pode ser dealer ou floor e se está ativo. Nada de
 * acesso ao site aqui: isso fica em Sistema → Usuários do site (permissão própria `acessos.*`).
 */
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
                        Grid::make(['default' => 1, 'sm' => 3])
                            ->columnSpanFull()
                            ->schema([
                                Toggle::make('pode_ser_dealer')
                                    ->label('Pode ser dealer'),
                                Toggle::make('pode_ser_floor')
                                    ->label('Pode ser floor'),
                                Toggle::make('ativo')
                                    ->default(true)
                                    ->helperText('Inativo não aparece para confirmar jogadores.'),
                            ]),
                    ]),

                Textarea::make('observacoes')
                    ->label('Observações')
                    ->rows(3),
            ]);
    }
}
