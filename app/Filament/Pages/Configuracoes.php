<?php

namespace App\Filament\Pages;

use App\Models\RegraPremiacao;
use App\Support\Dinheiro;
use App\Support\PadroesDoJogo;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Tela 25: padrões de um jogo novo (PadroesDoJogo) e a regra de premiação do clube por número de
 * jogadores (`regras_premiacao`, usada pela Calculadora ao fechar entradas). `configuracoes.editar`.
 */
class Configuracoes extends Page
{
    protected string $view = 'filament.painel.configuracoes';

    protected static string|BackedEnum|null $navigationIcon = 'rh-gear';

    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 100;

    protected static ?string $navigationLabel = 'Padrões e premiação';

    protected static ?string $title = 'Configurações';

    protected static ?string $slug = 'configuracoes';

    /** @var array<string, mixed> */
    public ?array $dados = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('configuracoes.editar') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            ...PadroesDoJogo::todos(),
            'regras' => RegraPremiacao::query()->orderBy('jogadores_min')->get()
                ->map(fn (RegraPremiacao $regra): array => [
                    'jogadores_min' => $regra->jogadores_min,
                    'jogadores_max' => $regra->jogadores_max,
                    'percentuais' => collect($regra->percentuais)->map(fn ($p): string => str_replace('.', ',', (string) (float) $p))->join(' / '),
                ])
                ->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $entrada = fn (string $tipo, string $rotulo): Fieldset => Fieldset::make($rotulo)
            ->columns(2)
            ->schema([
                TextInput::make($tipo.'_valor')->label('Valor')->numeric()->minValue(0)->step(0.01)->prefix('R$'),
                TextInput::make($tipo.'_fichas')->label('Fichas')->integer()->minValue(0),
            ]);

        return $schema
            ->statePath('dados')
            ->components([
                Section::make('Padrões do jogo')
                    ->description('Já vêm preenchidos ao criar um jogo ou modelo. Os jogos que já existem não mudam.')
                    ->schema([
                        Grid::make(['md' => 3])->schema([
                            TimePicker::make('horario')->label('Horário')->seconds(false),
                            TextInput::make('lugares_por_mesa')->label('Lugares por mesa')->integer()->minValue(2)->maxValue(12)->required(),
                            TextInput::make('tamanho_mesa_final')->label('Mesa final')->integer()->minValue(2)->maxValue(12)->required()
                                ->helperText('Até que posição conta como mesa final.'),
                        ]),
                        Grid::make(['md' => 3])->schema([
                            $entrada('buyin', 'Buy-in'),
                            $entrada('rebuy', 'Rebuy'),
                            $entrada('addon', 'Add-on'),
                        ]),
                        Fieldset::make('Taxa padrão')
                            ->columns(2)
                            ->schema([
                                TextInput::make('taxa_nome')->label('Nome')->maxLength(60)->placeholder('Casa')
                                    ->requiredWith('taxa_percentual'),
                                TextInput::make('taxa_percentual')->label('Percentual')->numeric()->minValue(0)->maxValue(100)->step(0.01)->suffix('%')
                                    ->requiredWith('taxa_nome')
                                    ->helperText('Entra nas taxas do jogo novo: sai da premiação antes de dividir. Vazio: sem taxa.'),
                            ]),
                    ]),

                Section::make('Premiação pela regra do clube')
                    ->description('Percentuais de cada posição pelo número de jogadores com buy-in. Valem para os torneios "pela regra do clube" ao fechar as entradas; quem já fechou não muda.')
                    ->schema([
                        Repeater::make('regras')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('De')->markAsRequired(),
                                TableColumn::make('Até'),
                                TableColumn::make('Percentuais (1º / 2º / 3º…)')->markAsRequired(),
                            ])
                            ->schema([
                                TextInput::make('jogadores_min')->integer()->minValue(1)->required()->suffix('jogadores'),
                                TextInput::make('jogadores_max')->integer()->minValue(1)->placeholder('Sem limite')->suffix('jogadores'),
                                TextInput::make('percentuais')->required()->placeholder('50 / 30 / 20'),
                            ])
                            ->minItems(1)
                            ->reorderable(false)
                            ->addActionLabel('Faixa'),
                    ]),
            ]);
    }

    public function salvar(): void
    {
        $dados = $this->form->getState();
        $regras = $this->regras($dados['regras'] ?? []);

        DB::transaction(function () use ($dados, $regras): void {
            PadroesDoJogo::gravar([
                ...$dados,
                'lugares_por_mesa' => (int) $dados['lugares_por_mesa'],
                'tamanho_mesa_final' => (int) $dados['tamanho_mesa_final'],
                'buyin_fichas' => (int) ($dados['buyin_fichas'] ?? 0),
                'rebuy_fichas' => (int) ($dados['rebuy_fichas'] ?? 0),
                'addon_fichas' => (int) ($dados['addon_fichas'] ?? 0),
            ]);

            RegraPremiacao::query()->delete();
            foreach ($regras as $regra) {
                RegraPremiacao::query()->create($regra);
            }
        });

        Notification::make()->success()->title('Configurações salvas.')->send();
    }

    /**
     * Confere as faixas: começam em até 2 jogadores, seguem sem buraco nem sobreposição, a última fica
     * sem limite; percentuais positivos somando 100% e no máximo um prêmio por jogador da faixa.
     *
     * @param  array<array-key, array{jogadores_min: int|string, jogadores_max: int|string|null, percentuais: string}>  $linhas
     * @return list<array{jogadores_min: int, jogadores_max: ?int, percentuais: list<int|float>}>
     */
    private function regras(array $linhas): array
    {
        $erro = fn (string $mensagem) => throw ValidationException::withMessages(['dados.regras' => $mensagem]);

        $regras = collect($linhas)
            ->map(function (array $linha) use ($erro): array {
                $percentuais = collect(explode('/', $linha['percentuais']))->map(fn (string $p): string => str_replace(',', '.', trim($p)));
                $min = (int) $linha['jogadores_min'];

                if ($percentuais->contains(fn (string $p): bool => ! is_numeric($p) || (float) $p <= 0)) {
                    $erro("Percentuais da faixa a partir de {$min}: use números separados por \"/\" (ex.: 50 / 30 / 20).");
                }
                if ($percentuais->sum(fn (string $p): int => Dinheiro::centavos($p)) !== 10000) {
                    $erro("Os percentuais da faixa a partir de {$min} somam ".str_replace('.', ',', (string) $percentuais->sum(fn ($p) => (float) $p)).'%, não 100%.');
                }
                if ($percentuais->count() > max($min, 2)) {
                    $erro("A faixa a partir de {$min} jogadores paga mais posições do que tem jogador.");
                }

                return [
                    'jogadores_min' => $min,
                    'jogadores_max' => filled($linha['jogadores_max'] ?? null) ? (int) $linha['jogadores_max'] : null,
                    'percentuais' => $percentuais->map(fn (string $p): int|float => $p + 0)->all(),
                ];
            })
            ->sortBy('jogadores_min')
            ->values();

        if ($regras->first()['jogadores_min'] > 2) {
            $erro('A primeira faixa tem de começar em 1 ou 2 jogadores.');
        }

        foreach ($regras as $i => $regra) {
            $proxima = $regras->get($i + 1);

            if ($regra['jogadores_max'] !== null && $regra['jogadores_max'] < $regra['jogadores_min']) {
                $erro("A faixa a partir de {$regra['jogadores_min']} termina antes de começar.");
            }
            if ($proxima && $regra['jogadores_max'] === null) {
                $erro("Só a última faixa fica sem limite (a de {$regra['jogadores_min']} tem outra depois).");
            }
            if ($proxima && $proxima['jogadores_min'] !== $regra['jogadores_max'] + 1) {
                $erro("Depois de {$regra['jogadores_max']} jogadores a próxima faixa tem de começar em ".($regra['jogadores_max'] + 1).'.');
            }
            if (! $proxima && $regra['jogadores_max'] !== null) {
                $erro('A última faixa fica sem limite ("Até" vazio).');
            }
        }

        return $regras->all();
    }
}
