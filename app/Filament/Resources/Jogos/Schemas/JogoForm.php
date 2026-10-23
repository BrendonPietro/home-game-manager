<?php

namespace App\Filament\Resources\Jogos\Schemas;

use App\Enums\CalculoTaxa;
use App\Enums\DestinoItem;
use App\Enums\MetodoPremiacao;
use App\Enums\Modalidade;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Mesa;
use App\Models\RegraPremiacao;
use App\Models\Usuario;
use App\Support\Dinheiro;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tela 20: cadastro do jogo (docs/painel.md). Entradas, opcionais, taxas, premiação e mesas só mudam
 * no painel em rascunho e agendado; durante o jogo quem mexe é o Admin do site.
 */
class JogoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Grid::make(2)->schema([
                    ToggleButtons::make('modalidade')
                        ->options(Modalidade::opcoes())
                        ->default(Modalidade::Torneio->value)
                        ->inline()
                        ->required()
                        ->live()
                        ->disabled(fn (?Jogo $record): bool => ! self::estruturaAberta($record)),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (StatusJogo $state): string => $state->label())
                        ->color(fn (StatusJogo $state): string => self::corDoStatus($state))
                        ->visible(fn (?Jogo $record): bool => $record !== null),
                ]),

                Tabs::make('Jogo')
                    ->persistTabInQueryString()
                    ->tabs([
                        self::informacoes(),
                        self::dinheiro(),
                        self::premiacao(),
                        self::rankings(),
                        self::mesas(),
                    ]),
            ]);
    }

    /** Entradas, opcionais, taxas, premiação e mesas só se mexem antes de iniciar. */
    public static function estruturaAberta(?Jogo $jogo): bool
    {
        return $jogo === null || in_array($jogo->status, [StatusJogo::Rascunho, StatusJogo::Agendado], true);
    }

    public static function corDoStatus(StatusJogo $status): string
    {
        return match ($status) {
            StatusJogo::Rascunho, StatusJogo::Cancelado => 'gray',
            StatusJogo::Agendado => 'info',
            StatusJogo::EmAndamento => 'danger',
            StatusJogo::Finalizado => 'success',
        };
    }

    private static function eTorneio(Get $get): bool
    {
        $modalidade = $get('modalidade');

        return ($modalidade instanceof Modalidade ? $modalidade : Modalidade::tryFrom((string) $modalidade)) === Modalidade::Torneio;
    }

    private static function informacoes(): Tab
    {
        return Tab::make('Informações')
            ->columns(2)
            ->schema([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Poker Night #19')
                    ->columnSpanFull(),
                DatePicker::make('data')
                    ->required(),
                TimePicker::make('horario')
                    ->label('Horário')
                    ->seconds(false)
                    ->default('20:00'),
                Select::make('temporada_id')
                    ->label('Temporada')
                    ->relationship('temporada', 'nome')
                    ->preload()
                    ->placeholder('Sem temporada'),
                TextInput::make('lugares_por_mesa')
                    ->label('Lugares por mesa')
                    ->integer()
                    ->minValue(2)
                    ->maxValue(12)
                    ->default(9)
                    ->required(),
                TextInput::make('tamanho_mesa_final')
                    ->label('Mesa final')
                    ->integer()
                    ->minValue(2)
                    ->maxValue(12)
                    ->default(9)
                    ->required()
                    ->helperText('Até que posição conta como mesa final nos rankings.')
                    ->visible(fn (Get $get): bool => self::eTorneio($get)),
                TextInput::make('valor_ficha')
                    ->label('Valor da ficha')
                    ->numeric()
                    ->step(0.0001)
                    ->minValue(0.0001)
                    ->prefix('R$')
                    ->required()
                    ->helperText('Cash-out = fichas finais × valor da ficha.')
                    ->visible(fn (Get $get): bool => ! self::eTorneio($get)),
                Textarea::make('observacoes')
                    ->label('Observações')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    private static function dinheiro(): Tab
    {
        return Tab::make('Dinheiro')
            ->disabled(fn (?Jogo $record): bool => ! self::estruturaAberta($record))
            ->schema([
                Section::make('Entradas')
                    ->description('Vão 100% para a premiação. O + adiciona uma variação (ex.: rebuy duplo). Jogo sem rebuy ou sem add-on: desative a linha padrão.')
                    ->schema([
                        self::entradas(TipoItem::Buyin, 'Buy-in', limite: 1),
                        self::entradas(TipoItem::Rebuy, 'Rebuy', limite: null),
                        self::entradas(TipoItem::Addon, 'Add-on', limite: 1)
                            ->visible(fn (Get $get): bool => self::eTorneio($get)),
                    ]),
                Section::make('Opcionais')
                    ->description('Opcional de fichas, churrasco… O destino diz quanto do valor vai para a premiação.')
                    ->schema([self::opcionais()]),
                Section::make('Taxas')
                    ->description('Saem da premiação antes de dividir (dealers, casa…).')
                    ->schema([self::taxas()]),
            ]);
    }

    private static function entradas(TipoItem $tipo, string $rotulo, ?int $limite): Repeater
    {
        return Repeater::make('itens_'.$tipo->value)
            ->label($rotulo)
            ->relationship('itens', fn (Builder $query) => $query->where('tipo', $tipo))
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'tipo' => $tipo])
            ->table([
                TableColumn::make('Nome')->markAsRequired(),
                TableColumn::make('Valor')->markAsRequired(),
                TableColumn::make('Fichas'),
                TableColumn::make('Limite por jogador'),
                TableColumn::make('Ativo'),
            ])
            ->schema([
                Hidden::make('padrao')->default(false),
                TextInput::make('nome')->required()->maxLength(60),
                // Linha desativada (jogo sem rebuy, por exemplo) não precisa de valor.
                TextInput::make('valor')
                    ->required(fn (Get $get): bool => (bool) $get('ativo'))
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('R$')
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 0),
                TextInput::make('fichas')->integer()->minValue(0)->default(0),
                TextInput::make('limite_por_jogador')->integer()->minValue(1)->placeholder('Sem limite'),
                Toggle::make('ativo')->default(true),
            ])
            ->default([[
                'padrao' => true,
                'nome' => $rotulo,
                'valor' => null,
                'fichas' => 0,
                'limite_por_jogador' => $limite,
                'ativo' => true,
            ]])
            ->orderColumn('ordem')
            ->addActionLabel('Variação de '.$rotulo)
            // A linha padrão não se apaga: desativa.
            ->deleteAction(fn (Action $action) => $action->hidden(
                fn (array $arguments, Repeater $component): bool => (bool) ($component->getRawItemState($arguments['item'])['padrao'] ?? false),
            ));
    }

    private static function opcionais(): Repeater
    {
        $porEntrada = [TipoItem::Buyin->value, TipoItem::Rebuy->value, TipoItem::Addon->value];

        return Repeater::make('opcionais')
            ->hiddenLabel()
            ->relationship('itens', fn (Builder $query) => $query->where('tipo', TipoItem::Opcional))
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...$data, 'tipo' => TipoItem::Opcional])
            ->table([
                TableColumn::make('Nome')->markAsRequired(),
                TableColumn::make('Valor')->markAsRequired(),
                TableColumn::make('Fichas'),
                TableColumn::make('Compra'),
                TableColumn::make('Destino'),
                TableColumn::make('Obrigatório'),
                TableColumn::make('Ativo'),
            ])
            ->schema([
                TextInput::make('nome')->required()->maxLength(60)->placeholder('Churrasco'),
                TextInput::make('valor')->required()->numeric()->minValue(0)->step(0.01)->prefix('R$'),
                TextInput::make('fichas')->integer()->minValue(0)->default(0),
                Select::make('compravel_com')
                    ->options(['entrada' => 'Junto de cada entrada', 'jogador' => '1 por jogador'])
                    ->default('entrada')
                    ->selectablePlaceholder(false)
                    ->formatStateUsing(fn ($state): string => is_array($state) && in_array('jogador', $state, true) ? 'jogador' : 'entrada')
                    ->dehydrateStateUsing(fn (?string $state): array => $state === 'jogador' ? ['jogador'] : $porEntrada),
                Select::make('destino')
                    ->options([
                        DestinoItem::Fora->value => 'Não vai',
                        DestinoItem::Premiacao->value => 'Vai',
                        DestinoItem::Metade->value => '50/50',
                    ])
                    ->default(DestinoItem::Fora->value)
                    ->selectablePlaceholder(false)
                    ->required(),
                Toggle::make('obrigatorio'),
                Toggle::make('ativo')->default(true),
            ])
            ->defaultItems(0)
            ->orderColumn('ordem')
            ->addActionLabel('Opcional');
    }

    private static function taxas(): Repeater
    {
        $percentual = fn (Get $get): bool => $get('calculo') === CalculoTaxa::Percentual->value || $get('calculo') === CalculoTaxa::Percentual;

        return Repeater::make('taxas')
            ->hiddenLabel()
            ->relationship()
            ->table([
                TableColumn::make('Nome')->markAsRequired(),
                TableColumn::make('Cálculo')->markAsRequired(),
                TableColumn::make('Valor')->markAsRequired(),
                TableColumn::make('Quantidade'),
            ])
            ->schema([
                TextInput::make('nome')->required()->maxLength(60)->placeholder('Dealers'),
                Select::make('calculo')
                    ->options(CalculoTaxa::opcoes())
                    ->default(CalculoTaxa::Fixo->value)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live(),
                TextInput::make('valor')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix(fn (Get $get): ?string => $percentual($get) ? null : 'R$')
                    ->suffix(fn (Get $get): ?string => $percentual($get) ? '%' : null)
                    ->maxValue(fn (Get $get): ?int => $percentual($get) ? 100 : null),
                TextInput::make('quantidade')
                    ->integer()
                    ->minValue(1)
                    ->default(1)
                    ->visible(fn (Get $get): bool => in_array($get('calculo'), [CalculoTaxa::PorUnidade->value, CalculoTaxa::PorUnidade], true)),
            ])
            ->defaultItems(0)
            ->orderColumn('ordem')
            ->addActionLabel('Taxa');
    }

    private static function premiacao(): Tab
    {
        $manual = fn (Get $get): bool => in_array($get('metodo_premiacao'), [MetodoPremiacao::Manual->value, MetodoPremiacao::Manual], true);

        return Tab::make('Premiação')
            ->visible(fn (Get $get): bool => self::eTorneio($get))
            ->disabled(fn (?Jogo $record): bool => ! self::estruturaAberta($record))
            ->schema([
                ToggleButtons::make('metodo_premiacao')
                    ->label('Como dividir o prize pool')
                    ->options([
                        MetodoPremiacao::Regra->value => 'Pela regra do clube',
                        MetodoPremiacao::Manual->value => 'Manual',
                    ])
                    ->default(MetodoPremiacao::Regra->value)
                    ->inline()
                    ->required()
                    ->live(),
                Text::make(fn (): string => 'Pelo número de jogadores: '.self::resumoDasRegras().'. Os valores são gravados ao fechar entradas.')
                    ->visible(fn (Get $get): bool => ! $manual($get)),
                Repeater::make('premiacoes')
                    ->label('Percentual por posição (na ordem: 1º, 2º, 3º…)')
                    ->relationship()
                    ->simple(
                        TextInput::make('percentual')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                    )
                    ->orderColumn('posicao')
                    ->minItems(1)
                    ->defaultItems(0)
                    ->addActionLabel('Posição')
                    ->visible($manual)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $soma = collect($value)->sum(fn ($item) => Dinheiro::centavos(is_array($item) ? ($item['percentual'] ?? null) : $item));

                        if ($soma !== 10000) {
                            // 9250 → "92,5"
                            $somaTexto = str_replace('.', ',', rtrim(rtrim(Dinheiro::decimal($soma), '0'), '.'));
                            $fail("Os percentuais precisam somar 100% (agora somam {$somaTexto}%).");
                        }
                    }),
            ]);
    }

    private static function resumoDasRegras(): string
    {
        return RegraPremiacao::query()->orderBy('jogadores_min')->get()
            ->map(fn (RegraPremiacao $regra): string => match (true) {
                $regra->jogadores_max === null => "{$regra->jogadores_min}+",
                $regra->jogadores_min <= 1 => "até {$regra->jogadores_max}",
                default => "{$regra->jogadores_min}–{$regra->jogadores_max}",
            }.' → '.implode('/', $regra->percentuais))
            ->implode(' · ') ?: 'nenhuma regra cadastrada';
    }

    private static function rankings(): Tab
    {
        return Tab::make('Rankings')
            ->schema([
                Select::make('rankings')
                    ->label('Vale ponto em')
                    ->relationship('rankings', 'nome', fn (Builder $query) => $query->where('ativo', true))
                    ->multiple()
                    ->preload()
                    ->helperText('Os pontos são gravados ao finalizar o jogo.'),
            ]);
    }

    private static function mesas(): Tab
    {
        return Tab::make('Mesas')
            ->disabled(fn (?Jogo $record): bool => ! self::estruturaAberta($record))
            ->schema([
                Select::make('floor_id')
                    ->label('Floor')
                    ->relationship('floor', 'nome', fn (Builder $query) => $query->where('pode_ser_floor', true)->where('ativo', true))
                    ->searchable()
                    ->preload()
                    ->placeholder('Sem floor'),
                Repeater::make('mesas')
                    ->relationship()
                    ->table([
                        TableColumn::make('Mesa')->markAsRequired(),
                        TableColumn::make('Dealer'),
                        TableColumn::make('Lugares')->markAsRequired(),
                    ])
                    ->schema([
                        Select::make('mesa_id')
                            ->options(fn (?string $state) => Mesa::query()
                                ->where(fn (Builder $q) => $q->where('ativa', true)->orWhere('id', $state))
                                ->orderBy('nome')
                                ->pluck('nome', 'id'))
                            ->required()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if ($mesa = Mesa::query()->find($state)) {
                                    $set('lugares', $mesa->lugares);
                                }
                            }),
                        Select::make('dealer_id')
                            ->options(fn (?string $state) => Usuario::query()
                                ->where(fn (Builder $q) => $q->where('pode_ser_dealer', true)->where('ativo', true)->orWhere('id', $state))
                                ->orderBy('nome')
                                ->pluck('nome', 'id'))
                            ->searchable()
                            ->placeholder('Sem dealer'),
                        TextInput::make('lugares')
                            ->integer()
                            ->minValue(2)
                            ->maxValue(12)
                            ->default(9)
                            ->required(),
                    ])
                    ->defaultItems(0)
                    ->orderColumn('ordem')
                    ->addActionLabel('Mesa')
                    // Mesa com jogadores sentados não sai daqui: troque os lugares no site antes.
                    ->deleteAction(fn (Action $action) => $action->hidden(
                        fn (array $arguments): bool => str_starts_with($arguments['item'], 'record-')
                            && JogoMesa::query()->whereKey(substr($arguments['item'], 7))->whereHas('participantes')->exists(),
                    )),
            ]);
    }
}
