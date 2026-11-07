<?php

namespace App\Filament\Resources\Jogos\Schemas;

use App\Enums\CalculoTaxa;
use App\Enums\MetodoPremiacao;
use App\Enums\Modalidade;
use App\Enums\StatusJogo;
use App\Enums\TipoItem;
use App\Models\EstruturaBlinds;
use App\Models\Item;
use App\Models\Jogo;
use App\Models\JogoMesa;
use App\Models\Mesa;
use App\Models\Premiacao;
use App\Models\RegraPremiacao;
use App\Models\Taxa;
use App\Models\Usuario;
use App\Services\Jogo\Calculadora;
use App\Services\Jogo\ResumoFinanceiro;
use App\Support\Dinheiro;
use App\Support\PadroesDoJogo;
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
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Tela 20: cadastro do jogo (docs/painel.md). Entradas, opcionais, taxas, premiação, blinds e mesas só mudam
 * no painel em rascunho e agendado; durante o jogo quem mexe é o Admin do site.
 */
class JogoForm
{
    /** Atalhos do percentual da premiação de cada item (o resto se digita em "Outro"). */
    private const ATALHOS_DA_PREMIACAO = ['100' => '100%', '50' => '50%', '0' => '0%'];

    /** @param  bool  $modelo  torneio modelo (ModeloResource): nome do modelo no lugar de nome e data, sem status */
    public static function configure(Schema $schema, bool $modelo = false): Schema
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
                        ->visible(fn (?Jogo $record): bool => $record !== null && ! $modelo),
                ]),

                Tabs::make('Jogo')
                    ->persistTabInQueryString()
                    ->tabs([
                        self::informacoes($modelo),
                        self::blinds(),
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

    private static function informacoes(bool $modelo): Tab
    {
        return Tab::make('Informações')
            ->columns(2)
            ->schema([
                TextInput::make('nome_modelo')
                    ->label('Nome do modelo')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Torneio de sábado')
                    ->helperText('Cada jogo criado a partir dele recebe nome, data e horário próprios.')
                    ->columnSpanFull()
                    ->visible($modelo),
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Poker Night #19')
                    ->columnSpanFull()
                    ->visible(! $modelo),
                DatePicker::make('data')
                    ->required()
                    ->visible(! $modelo),
                TimePicker::make('horario')
                    ->label('Horário')
                    ->seconds(false)
                    ->default(fn (): ?string => PadroesDoJogo::valor('horario')),
                Select::make('temporada_id')
                    ->label('Temporada')
                    ->relationship('temporada', 'nome')
                    ->preload()
                    ->placeholder('Sem temporada'),
                // O tipo de evento nas Métricas: criar a partir do modelo já preenche.
                Select::make('modelo_id')
                    ->label('Tipo de evento (modelo)')
                    ->options(fn (): array => Jogo::query()->modelos()->orderBy('nome_modelo')->pluck('nome_modelo', 'id')->all())
                    ->placeholder('Sem modelo')
                    ->helperText('Compara as médias por tipo de torneio em Métricas.')
                    ->visible(! $modelo),
                TextInput::make('lugares_por_mesa')
                    ->label('Lugares por mesa')
                    ->integer()
                    ->minValue(2)
                    ->maxValue(12)
                    ->default(fn (): int => (int) PadroesDoJogo::valor('lugares_por_mesa'))
                    ->required(),
                TextInput::make('tamanho_mesa_final')
                    ->label('Mesa final')
                    ->integer()
                    ->minValue(2)
                    ->maxValue(12)
                    ->default(fn (): int => (int) PadroesDoJogo::valor('tamanho_mesa_final'))
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

    /** v2: estrutura de blinds do torneio. Sem estrutura, o nível e os blinds são digitados na noite (v1). */
    private static function blinds(): Tab
    {
        return Tab::make('Blinds')
            ->visible(fn (Get $get): bool => self::eTorneio($get))
            ->disabled(fn (?Jogo $record): bool => ! self::estruturaAberta($record))
            ->schema([
                Select::make('estrutura_blinds_id')
                    ->label('Estrutura de blinds')
                    ->options(fn (?string $state) => EstruturaBlinds::query()
                        ->where(fn (Builder $q) => $q->where('ativo', true)->orWhere('id', $state))
                        ->orderBy('nome')
                        ->pluck('nome', 'id'))
                    ->placeholder('Sem estrutura: nível e blinds digitados na noite')
                    ->live(), // cash game não tem estrutura: o Jogo limpa ao salvar
                Text::make(fn (Get $get): ?string => EstruturaBlinds::query()->find($get('estrutura_blinds_id'))?->descricao)
                    ->visible(fn (Get $get): bool => filled($get('estrutura_blinds_id'))),
                View::make('filament.painel.estrutura-niveis')
                    ->viewData(fn (Get $get): array => [
                        'linhas' => EstruturaBlinds::linhas(EstruturaBlinds::query()->find($get('estrutura_blinds_id'))?->niveis ?? []),
                    ])
                    ->visible(fn (Get $get): bool => filled($get('estrutura_blinds_id'))),
            ]);
    }

    private static function dinheiro(): Tab
    {
        return Tab::make('Dinheiro')
            ->disabled(fn (?Jogo $record): bool => ! self::estruturaAberta($record))
            ->schema([
                Section::make('Entradas')
                    ->description('Premiação diz quanto de cada entrada vai para o prize pool (100% é o normal; Outro deixa digitar, ex.: 75%). O + adiciona uma variação (ex.: rebuy duplo). Jogo sem rebuy ou sem add-on: desative a linha padrão.')
                    ->schema([
                        self::entradas(TipoItem::Buyin, 'Buy-in', limite: 1),
                        self::entradas(TipoItem::Rebuy, 'Rebuy', limite: null),
                        self::entradas(TipoItem::Addon, 'Add-on', limite: 1)
                            ->visible(fn (Get $get): bool => self::eTorneio($get)),
                    ]),
                Section::make('Opcionais')
                    ->description('Opcional de fichas, churrasco… Premiação diz quanto do valor vai para o prize pool (0% fica todo com a casa).')
                    ->schema([self::opcionais()]),
                Section::make('Taxas')
                    ->description('Saem da premiação antes de dividir (dealers, casa…).')
                    ->schema([self::taxas()]),
                self::simulacao(),
            ]);
    }

    /**
     * Simulação do prize pool com o formulário como está (antes de salvar): N jogadores com o buy-in
     * padrão, rebuys e add-ons padrão e os opcionais obrigatórios, pela Calculadora::simular.
     */
    private static function simulacao(): Section
    {
        $quantos = fn (string $rotulo, string $exemplo): TextInput => TextInput::make('simular_'.Str::slug($rotulo, '_'))
            ->label($rotulo)
            ->integer()
            ->minValue(0)
            ->maxValue(500)
            ->placeholder($exemplo)
            ->dehydrated(false)
            ->live(debounce: 500);

        return Section::make('Simulação do prize pool')
            ->description('Estimativa com o cadastro como está agora (buy-in, rebuy e add-on padrão e os opcionais obrigatórios). Não grava nada.')
            ->visible(fn (?Jogo $record): bool => self::estruturaAberta($record))
            ->schema([
                Grid::make(['md' => 3])->schema([
                    $quantos('Jogadores', '12'),
                    $quantos('Rebuys', '0'),
                    $quantos('Add-ons', '0')->visible(fn (Get $get): bool => self::eTorneio($get)),
                ]),
                View::make('filament.painel.simulacao-prize-pool')
                    ->viewData(fn (Get $get): array => ['resumo' => self::simular($get), 'torneio' => self::eTorneio($get)]),
            ]);
    }

    private static function simular(Get $get): ResumoFinanceiro
    {
        $numero = fn (mixed $valor): string => is_numeric($valor) ? (string) $valor : '0';
        $quantos = fn (string $campo, int $padrao): int => is_numeric($get($campo)) ? max(0, (int) $get($campo)) : $padrao;

        $jogo = new Jogo([
            'modalidade' => $get('modalidade') ?: Modalidade::Torneio->value,
            'metodo_premiacao' => $get('metodo_premiacao') ?: MetodoPremiacao::Regra->value,
        ]);

        $itens = collect(TipoItem::entradas())
            ->flatMap(fn (TipoItem $tipo): array => array_map(fn (array $linha): Item => new Item([
                'tipo' => $tipo,
                'valor' => $numero($linha['valor'] ?? null),
                'padrao' => (bool) ($linha['padrao'] ?? false),
                'ativo' => (bool) ($linha['ativo'] ?? true),
                'percentual_premiacao' => self::percentualDaPremiacao($linha, 100),
            ]), array_values($get('itens_'.$tipo->value) ?? [])))
            ->merge(array_map(fn (array $linha): Item => new Item([
                'tipo' => TipoItem::Opcional,
                'valor' => $numero($linha['valor'] ?? null),
                'percentual_premiacao' => self::percentualDaPremiacao($linha, 0),
                'obrigatorio' => (bool) ($linha['obrigatorio'] ?? false),
                'ativo' => (bool) ($linha['ativo'] ?? true),
                'compravel_com' => ($linha['compravel_com'] ?? 'entrada') === 'jogador'
                    ? [Item::COMPRAVEL_POR_JOGADOR]
                    : array_map(fn (TipoItem $tipo): string => $tipo->value, TipoItem::entradas()),
            ]), array_values($get('opcionais') ?? [])));

        $jogo->setRelation('taxas', collect(array_values($get('taxas') ?? []))->map(fn (array $linha): Taxa => new Taxa([
            'nome' => $linha['nome'] ?: 'Taxa',
            'calculo' => $linha['calculo'] ?? CalculoTaxa::Fixo->value,
            'valor' => $numero($linha['valor'] ?? null),
            'quantidade' => $linha['quantidade'] ?? 1,
        ])));

        $jogo->setRelation('premiacoes', collect(array_values($get('premiacoes') ?? []))->map(fn (mixed $item, int $i): Premiacao => new Premiacao([
            'posicao' => $i + 1,
            'percentual' => $numero(is_array($item) ? ($item['percentual'] ?? null) : $item),
        ])));

        return app(Calculadora::class)->simular($jogo, $quantos('simular_jogadores', 12), $quantos('simular_rebuys', 0), $quantos('simular_add_ons', 0), $itens);
    }

    private static function entradas(TipoItem $tipo, string $rotulo, ?int $limite): Repeater
    {
        return Repeater::make('itens_'.$tipo->value)
            ->label($rotulo)
            ->relationship('itens', fn (Builder $query) => $query->where('tipo', $tipo))
            ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => self::separarPremiacao($data))
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...self::juntarPremiacao($data, 100), 'tipo' => $tipo])
            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::juntarPremiacao($data, 100))
            ->table([
                TableColumn::make('Nome')->markAsRequired(),
                TableColumn::make('Valor')->markAsRequired(),
                TableColumn::make('Fichas'),
                TableColumn::make('Premiação'),
                TableColumn::make('%'),
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
                    ->live(onBlur: true) // atualiza a simulação do prize pool
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? $state : 0),
                TextInput::make('fichas')->integer()->minValue(0)->default(0),
                ...self::camposDaPremiacao(100),
                TextInput::make('limite_por_jogador')->integer()->minValue(1)->placeholder('Sem limite'),
                Toggle::make('ativo')->default(true)->live(),
            ])
            // Valor e fichas da linha padrão vêm das Configurações (PadroesDoJogo).
            ->default(fn (): array => [[
                'padrao' => true,
                'nome' => $rotulo,
                'valor' => PadroesDoJogo::valor($tipo->value.'_valor'),
                'fichas' => (int) PadroesDoJogo::valor($tipo->value.'_fichas'),
                'premiacao' => '100',
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
            ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => self::separarPremiacao($data))
            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => [...self::juntarPremiacao($data, 0), 'tipo' => TipoItem::Opcional])
            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::juntarPremiacao($data, 0))
            ->table([
                TableColumn::make('Nome')->markAsRequired(),
                TableColumn::make('Valor')->markAsRequired(),
                TableColumn::make('Fichas'),
                TableColumn::make('Compra'),
                TableColumn::make('Premiação'),
                TableColumn::make('%'),
                TableColumn::make('Obrigatório'),
                TableColumn::make('Ativo'),
            ])
            ->schema([
                TextInput::make('nome')->required()->maxLength(60)->placeholder('Churrasco'),
                TextInput::make('valor')->required()->numeric()->minValue(0)->step(0.01)->prefix('R$')->live(onBlur: true),
                TextInput::make('fichas')->integer()->minValue(0)->default(0),
                Select::make('compravel_com')
                    ->options(['entrada' => 'Junto de cada entrada', 'jogador' => '1 por jogador'])
                    ->default('entrada')
                    ->selectablePlaceholder(false)
                    ->live()
                    ->formatStateUsing(fn ($state): string => is_array($state) && in_array('jogador', $state, true) ? 'jogador' : 'entrada')
                    ->dehydrateStateUsing(fn (?string $state): array => $state === 'jogador' ? ['jogador'] : $porEntrada),
                ...self::camposDaPremiacao(0),
                Toggle::make('obrigatorio')->live(),
                Toggle::make('ativo')->default(true)->live(),
            ])
            ->defaultItems(0)
            ->orderColumn('ordem')
            ->addActionLabel('Opcional');
    }

    /**
     * Quanto do item vai para a premiação: 100 / 50 / 0 % prontos e "Outro" para digitar (ex.: 75%).
     * Os dois campos viram a coluna `percentual_premiacao` (juntarPremiacao / separarPremiacao).
     *
     * @return list<Select|TextInput>
     */
    private static function camposDaPremiacao(int $padrao): array
    {
        return [
            Select::make('premiacao')
                ->options(self::ATALHOS_DA_PREMIACAO + ['outro' => 'Outro'])
                ->default((string) $padrao)
                ->selectablePlaceholder(false)
                ->required()
                ->live(),
            TextInput::make('premiacao_outro')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->step(0.01)
                ->suffix('%')
                ->placeholder('75')
                ->required(fn (Get $get): bool => $get('premiacao') === 'outro')
                ->visible(fn (Get $get): bool => $get('premiacao') === 'outro')
                ->live(onBlur: true),
        ];
    }

    /** O percentual da linha do formulário (atalho ou o digitado), entre 0 e 100, como o banco guarda ('75.00'). */
    public static function percentualDaPremiacao(array $linha, int $padrao): string
    {
        $atalho = (string) ($linha['premiacao'] ?? $padrao);
        $valor = $atalho === 'outro' ? ($linha['premiacao_outro'] ?? null) : $atalho;
        $valor = is_numeric($valor) ? max(0, min(100, (float) $valor)) : $padrao;

        return number_format($valor, 2, '.', '');
    }

    /** Do formulário para o banco. */
    private static function juntarPremiacao(array $data, int $padrao): array
    {
        $data['percentual_premiacao'] = self::percentualDaPremiacao($data, $padrao);
        unset($data['premiacao'], $data['premiacao_outro']);

        return $data;
    }

    /** Do banco para os dois campos: 100 / 50 / 0 viram o atalho; o resto, "Outro" com o número. */
    private static function separarPremiacao(array $data): array
    {
        $percentual = number_format((float) ($data['percentual_premiacao'] ?? 100), 2, '.', '');
        $atalho = (string) (int) $percentual;
        $eAtalho = isset(self::ATALHOS_DA_PREMIACAO[$atalho]) && $percentual === $atalho.'.00';

        return [
            ...$data,
            'premiacao' => $eAtalho ? $atalho : 'outro',
            'premiacao_outro' => $eAtalho ? null : rtrim(rtrim($percentual, '0'), '.'),
        ];
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
                    ->maxValue(fn (Get $get): ?int => $percentual($get) ? 100 : null)
                    ->live(onBlur: true),
                TextInput::make('quantidade')
                    ->integer()
                    ->minValue(1)
                    ->default(1)
                    ->live(onBlur: true)
                    ->visible(fn (Get $get): bool => in_array($get('calculo'), [CalculoTaxa::PorUnidade->value, CalculoTaxa::PorUnidade], true)),
            ])
            // Taxa padrão das Configurações (ex.: Casa 10%), se houver.
            ->default(fn (): array => filled(PadroesDoJogo::valor('taxa_nome')) && filled(PadroesDoJogo::valor('taxa_percentual'))
                ? [['nome' => PadroesDoJogo::valor('taxa_nome'), 'calculo' => CalculoTaxa::Percentual->value, 'valor' => PadroesDoJogo::valor('taxa_percentual'), 'quantidade' => 1]]
                : [])
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
                            ->suffix('%')
                            ->live(onBlur: true),
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
