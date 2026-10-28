<?php

namespace App\Filament\Resources\Rankings\Schemas;

use App\Enums\Periodicidade;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/** Regras de pontos de um ranking. Mudar depois não mexe nos pontos já gravados dos jogos finalizados. */
class RankingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Ranking')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('nome')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Anual 2026')
                            ->columnSpanFull(),
                        Select::make('periodicidade')
                            ->options(Periodicidade::opcoes())
                            ->default(Periodicidade::Anual->value)
                            ->required(),
                        Toggle::make('ativo')
                            ->default(true)
                            ->helperText('Só ranking ativo aparece no cadastro do jogo e recebe pontos.')
                            ->inline(false),
                        DatePicker::make('inicio')
                            ->label('Começa em')
                            ->helperText('Vazio: desde sempre.'),
                        DatePicker::make('fim')
                            ->label('Termina em')
                            ->afterOrEqual('inicio')
                            ->helperText('Vazio: sem fim. A classificação soma os jogos dentro do período.'),
                    ]),

                Section::make('Pontos por posição')
                    ->description('Na ordem: 1º, 2º, 3º… Só as posições listadas pontuam.')
                    ->schema([
                        Repeater::make('pontuacoes')
                            ->hiddenLabel()
                            ->relationship()
                            ->simple(
                                TextInput::make('pontos')
                                    ->required()
                                    ->integer()
                                    ->minValue(0)
                                    ->suffix('pts'),
                            )
                            ->orderColumn('posicao')
                            ->defaultItems(0)
                            ->addActionLabel('Posição'),
                    ]),

                Section::make('Bônus')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('ft_pontua')
                                ->label('Mesa final pontua')
                                ->live()
                                ->inline(false),
                            TextInput::make('pontos_mesa_final')
                                ->label('Pontos por chegar à mesa final')
                                ->integer()
                                ->minValue(0)
                                ->default(0)
                                ->helperText('Mesa final = até a posição "Mesa final" do jogo (padrão 9).')
                                ->visible(fn (Get $get): bool => (bool) $get('ft_pontua')),
                        ]),
                        Grid::make(2)->schema([
                            Toggle::make('participacao_pontua')
                                ->label('Participação pontua')
                                ->live()
                                ->inline(false),
                            TextInput::make('pontos_participacao')
                                ->label('Pontos por jogar')
                                ->integer()
                                ->minValue(0)
                                ->default(0)
                                ->visible(fn (Get $get): bool => (bool) $get('participacao_pontua')),
                        ]),
                    ]),
            ]);
    }
}
