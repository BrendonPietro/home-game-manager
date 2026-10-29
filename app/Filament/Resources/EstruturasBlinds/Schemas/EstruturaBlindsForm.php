<?php

namespace App\Filament\Resources\EstruturasBlinds\Schemas;

use App\Models\EstruturaBlinds;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

/**
 * Níveis em ordem (arraste para reordenar). Intervalo não tem blinds, só nome e duração, e não conta
 * no número do nível. A prévia embaixo numera os níveis e mostra quando cada um começa.
 */
class EstruturaBlindsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Estrutura')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('Turbo 15 min'),
                        Toggle::make('ativo')
                            ->default(true)
                            ->helperText('Só estrutura ativa aparece no cadastro do jogo.')
                            ->inline(false),
                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(2)
                            ->placeholder('Stack inicial, quando acaba o rebuy…')
                            ->columnSpanFull(),
                    ]),

                Section::make('Níveis')
                    ->description('Na ordem do relógio. Ante 0 = sem ante. Intervalo não conta no número do nível.')
                    ->schema([
                        Repeater::make('niveis')
                            ->hiddenLabel()
                            ->relationship()
                            ->table([
                                TableColumn::make('Intervalo')->width('6rem'),
                                TableColumn::make('Small blind'),
                                TableColumn::make('Big blind'),
                                TableColumn::make('Ante'),
                                TableColumn::make('Minutos')->markAsRequired(),
                                TableColumn::make('Nome do intervalo'),
                            ])
                            ->schema([
                                Toggle::make('e_intervalo')->default(false)->live(),
                                TextInput::make('small_blind')
                                    ->integer()
                                    ->minValue(1)
                                    ->required(fn (Get $get): bool => ! $get('e_intervalo'))
                                    ->disabled(fn (Get $get): bool => (bool) $get('e_intervalo'))
                                    ->live(onBlur: true),
                                TextInput::make('big_blind')
                                    ->integer()
                                    ->minValue(1)
                                    ->gte('small_blind')
                                    ->required(fn (Get $get): bool => ! $get('e_intervalo'))
                                    ->disabled(fn (Get $get): bool => (bool) $get('e_intervalo'))
                                    ->live(onBlur: true),
                                TextInput::make('ante')
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->disabled(fn (Get $get): bool => (bool) $get('e_intervalo'))
                                    ->live(onBlur: true),
                                TextInput::make('duracao_minutos')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(240)
                                    ->default(15)
                                    ->required()
                                    ->suffix('min')
                                    ->live(onBlur: true),
                                TextInput::make('nome_intervalo')
                                    ->maxLength(40)
                                    ->placeholder(fn (Get $get): string => $get('e_intervalo') ? 'Intervalo' : '—')
                                    ->disabled(fn (Get $get): bool => ! $get('e_intervalo'))
                                    ->live(onBlur: true),
                            ])
                            ->orderColumn('ordem')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Nível')
                            ->rule(fn (): Closure => function (string $attribute, $niveis, Closure $fail): void {
                                if (collect($niveis)->every(fn (array $nivel): bool => (bool) ($nivel['e_intervalo'] ?? false))) {
                                    $fail('Cadastre ao menos um nível com blinds.');
                                }
                            }),
                    ]),

                Section::make('Como fica')
                    ->description('Começa em: tempo de relógio desde o nível 1.')
                    ->collapsible()
                    ->schema([
                        View::make('filament.painel.estrutura-niveis')
                            ->viewData(fn (Get $get): array => ['linhas' => EstruturaBlinds::linhas($get('niveis') ?? [])]),
                    ]),
            ]);
    }
}
