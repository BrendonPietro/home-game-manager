<?php

namespace App\Filament\Resources\Usuarios\Schemas;

use App\Models\Usuario;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

/**
 * Cadastros → Jogadores: os dados da pessoa e o que ela faz na noite. O acesso ao site (e-mail, senha e
 * grupos) fica em Sistema → Usuários do site, com a permissão própria `acessos.*`.
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

                Text::make(fn (?Usuario $record): string => $record?->acessa_site
                    ? 'Entra no site com '.$record->email.'. E-mail, senha e grupos em Sistema → Usuários do site.'
                    : 'Para entrar no site de operação, dê acesso em Sistema → Usuários do site.')
                    ->color('gray'),

                Textarea::make('observacoes')
                    ->label('Observações')
                    ->rows(3),
            ]);
    }
}
