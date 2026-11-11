<?php

namespace App\Filament\Resources\EstruturasBlinds\Schemas;

use App\Models\EstruturaBlinds;
use App\Models\EstruturaNivel;
use App\Support\GeradorDeNiveis;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

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
                    ->key('secao-niveis')
                    ->description('Na ordem do relógio (arraste para trocar). Ante 0 = sem ante. Intervalo não conta no número do nível.')
                    ->schema([
                        Repeater::make('niveis')
                            ->hiddenLabel()
                            ->relationship()
                            // Celular em pé: cada nível vira um cartão de duas linhas (theme.css, .rh-niveis).
                            ->extraAttributes(['class' => 'rh-niveis'])
                            ->table([
                                TableColumn::make('Intervalo')->width('6.5rem'),
                                TableColumn::make('Small blind'),
                                TableColumn::make('Big blind'),
                                TableColumn::make('Ante'),
                                TableColumn::make('Minutos')->markAsRequired()->width('6.5rem'),
                                TableColumn::make('Nome do intervalo'),
                            ])
                            ->schema([
                                Toggle::make('e_intervalo')->label('Intervalo')->default(false)->live(),
                                TextInput::make('small_blind')
                                    ->label('Small')
                                    ->integer()
                                    ->minValue(1)
                                    ->required(fn (Get $get): bool => ! $get('e_intervalo'))
                                    ->hidden(fn (Get $get): bool => (bool) $get('e_intervalo'))
                                    ->live(onBlur: true),
                                TextInput::make('big_blind')
                                    ->label('Big')
                                    ->integer()
                                    ->minValue(1)
                                    ->gte('small_blind')
                                    ->required(fn (Get $get): bool => ! $get('e_intervalo'))
                                    ->hidden(fn (Get $get): bool => (bool) $get('e_intervalo'))
                                    ->live(onBlur: true),
                                TextInput::make('ante')
                                    ->label('Ante')
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0)
                                    ->hidden(fn (Get $get): bool => (bool) $get('e_intervalo'))
                                    ->live(onBlur: true),
                                TextInput::make('duracao_minutos')
                                    ->label('Minutos')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(240)
                                    ->default(15)
                                    ->required()
                                    ->live(onBlur: true),
                                TextInput::make('nome_intervalo')
                                    ->label('Nome do intervalo')
                                    ->maxLength(40)
                                    ->placeholder('Intervalo')
                                    ->visible(fn (Get $get): bool => (bool) $get('e_intervalo'))
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
                    ])
                    ->footerActions([self::gerarNiveis()]),

                Section::make('Como fica')
                    ->description('Começa em: tempo de relógio desde o nível 1.')
                    ->collapsible()
                    ->schema([
                        View::make('filament.painel.estrutura-niveis')
                            ->viewData(fn (Get $get): array => ['linhas' => EstruturaBlinds::linhas($get('niveis') ?? [])]),
                    ]),
            ]);
    }

    /**
     * "Gerar próximos níveis": continua a estrutura no padrão dos últimos níveis (GeradorDeNiveis), com
     * o padrão sugerido e editável e uma prévia. Os níveis entram no fim da lista e só gravam ao salvar.
     */
    private static function gerarNiveis(): Action
    {
        $temBlinds = fn (?array $niveis): bool => collect($niveis ?? [])
            ->contains(fn (array $n): bool => ! ($n['e_intervalo'] ?? false) && (int) ($n['big_blind'] ?? 0) > 0);

        return Action::make('gerarNiveis')
            ->label('Gerar próximos níveis')
            ->icon('rh-adicionar')
            ->color('gray')
            ->disabled(fn (Get $get): bool => ! $temBlinds($get('niveis')))
            ->tooltip(fn (Get $get): ?string => $temBlinds($get('niveis')) ? null : 'Preencha ao menos um nível com blinds.')
            ->modalHeading('Gerar próximos níveis')
            ->modalDescription('Continua a partir do último nível, no mesmo padrão. Depois dá para mexer em cada um.')
            ->modalSubmitActionLabel('Gerar')
            ->modalWidth('lg')
            ->fillForm(function (Get $get): array {
                $padrao = GeradorDeNiveis::padrao($get('niveis') ?? []);

                return [
                    'quantos' => 5,
                    'tipo' => $padrao['tipo'],
                    'valor' => $padrao['tipo'] === GeradorDeNiveis::DOBRAR ? null : $padrao['valor'],
                    'minutos' => $padrao['minutos'],
                    'intervalo_a_cada' => 0,
                    'minutos_intervalo' => $padrao['minutos_intervalo'],
                ];
            })
            ->schema(function (Get $get): array {
                $niveis = $get('niveis') ?? [];

                return [
                    Grid::make(['default' => 2])->schema([
                        TextInput::make('quantos')->label('Quantos níveis')->integer()->minValue(1)->maxValue(40)->required()->live(onBlur: true),
                        TextInput::make('minutos')->label('Minutos cada')->integer()->minValue(1)->maxValue(240)->required()->live(onBlur: true),
                    ]),
                    ToggleButtons::make('tipo')
                        ->label('O big blind vai')
                        ->options([
                            GeradorDeNiveis::DOBRAR => 'Dobrando',
                            GeradorDeNiveis::MULTIPLICAR => 'Multiplicando',
                            GeradorDeNiveis::SOMAR => 'Somando',
                        ])
                        ->inline()
                        ->required()
                        ->live(),
                    TextInput::make('valor')
                        ->label(fn (Get $get): string => $get('tipo') === GeradorDeNiveis::MULTIPLICAR ? 'Multiplicar por' : 'Somar ao big blind')
                        ->numeric()
                        ->minValue(fn (Get $get): float => $get('tipo') === GeradorDeNiveis::MULTIPLICAR ? 1.05 : 1)
                        ->step(fn (Get $get): float => $get('tipo') === GeradorDeNiveis::MULTIPLICAR ? 0.05 : 1)
                        ->prefix(fn (Get $get): string => $get('tipo') === GeradorDeNiveis::MULTIPLICAR ? '×' : '+')
                        ->required(fn (Get $get): bool => $get('tipo') !== GeradorDeNiveis::DOBRAR)
                        ->hidden(fn (Get $get): bool => $get('tipo') === GeradorDeNiveis::DOBRAR)
                        ->live(onBlur: true),
                    Grid::make(['default' => 2])->schema([
                        TextInput::make('intervalo_a_cada')
                            ->label('Intervalo a cada')
                            ->integer()
                            ->minValue(0)
                            ->suffix('níveis')
                            ->helperText('0 = sem intervalo')
                            ->live(onBlur: true),
                        TextInput::make('minutos_intervalo')
                            ->label('Minutos do intervalo')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(240)
                            ->hidden(fn (Get $get): bool => (int) $get('intervalo_a_cada') < 1)
                            ->live(onBlur: true),
                    ]),
                    Text::make(fn (Get $get): string => self::previa($niveis, $get)),
                ];
            })
            ->action(function (array $data, Get $get, Set $set): void {
                $niveis = $get('niveis') ?? [];

                try {
                    $novos = self::gerados($niveis, $data);
                } catch (InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                foreach ($novos as $nivel) {
                    $niveis[(string) Str::uuid()] = $nivel;
                }

                $set('niveis', $niveis);

                $intervalos = count(array_filter($novos, fn (array $n): bool => $n['e_intervalo']));
                $blinds = count($novos) - $intervalos;
                $texto = $blinds.($blinds === 1 ? ' nível' : ' níveis')
                    .($intervalos ? ' e '.$intervalos.($intervalos === 1 ? ' intervalo' : ' intervalos') : '');

                Notification::make()->success()->title("{$texto} no fim da lista. Confira e salve.")->send();
            });
    }

    /**
     * @param  array<array-key, array<string, mixed>>  $niveis
     * @param  array<string, mixed>  $dados
     * @return list<array<string, mixed>>
     */
    private static function gerados(array $niveis, array $dados): array
    {
        $tipo = $dados['tipo'] ?? GeradorDeNiveis::DOBRAR;

        return GeradorDeNiveis::gerar(
            $niveis,
            max(1, min(40, (int) ($dados['quantos'] ?? 5))),
            $tipo,
            $tipo === GeradorDeNiveis::DOBRAR ? 2 : ($dados['valor'] ?? 0),
            max(1, (int) ($dados['minutos'] ?? 15)),
            max(0, (int) ($dados['intervalo_a_cada'] ?? 0)),
            max(1, (int) ($dados['minutos_intervalo'] ?? 15)),
        );
    }

    /** "Vai gerar: 200 / 400 · 300 / 600 · Intervalo · …" com o que está no formulário do modal. */
    private static function previa(array $niveis, Get $get): string
    {
        try {
            $novos = self::gerados($niveis, [
                'quantos' => $get('quantos'),
                'tipo' => $get('tipo'),
                'valor' => $get('valor'),
                'minutos' => $get('minutos'),
                'intervalo_a_cada' => $get('intervalo_a_cada'),
                'minutos_intervalo' => $get('minutos_intervalo'),
            ]);
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return 'Vai gerar: '.collect($novos)
            ->map(fn (array $n): string => $n['e_intervalo']
                ? 'Intervalo'
                : EstruturaNivel::fichas($n['small_blind']).' / '.EstruturaNivel::fichas($n['big_blind']).($n['ante'] ? ' (ante '.EstruturaNivel::fichas($n['ante']).')' : ''))
            ->join(' · ');
    }
}
