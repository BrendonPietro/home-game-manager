<?php

namespace App\Filament\Pages;

use App\Enums\StatusJogo;
use App\Models\Jogo;
use App\Models\Usuario;
use App\Services\Relatorios\Exportacao;
use App\Services\Relatorios\Filtro;
use App\Services\Relatorios\Relatorio;
use App\Services\Relatorios\Relatorios as GeradorDeRelatorios;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Tela 24: relatórios de jogos, entradas, prize pools, premiações, aluguéis e arrecadação, com filtro
 * de período, jogo e jogador, prévia na tela e exportação em CSV, Excel e PDF (página para imprimir,
 * `Painel\ImprimirRelatorio`), com `relatorios.exportar`.
 */
class Relatorios extends Page
{
    protected string $view = 'filament.painel.relatorios';

    protected static string|BackedEnum|null $navigationIcon = 'rh-menu-relatorios';

    protected static string|UnitEnum|null $navigationGroup = 'Dashboards e relatórios';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Relatórios';

    protected static ?string $title = 'Relatórios';

    protected static ?string $slug = 'relatorios';

    #[Url]
    public string $relatorio = 'premiacoes';

    /** @var array<string, mixed> */
    public ?array $filtros = [];

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('relatorios.ver') ?? false;
    }

    public function mount(): void
    {
        if (! array_key_exists($this->relatorio, GeradorDeRelatorios::TIPOS)) {
            $this->relatorio = 'premiacoes';
        }

        $this->filtrosForm->fill(['periodo' => Filtro::periodoPadrao()]);
    }

    public function filtrosForm(Schema $schema): Schema
    {
        $personalizado = fn (Get $get): bool => $get('periodo') === 'personalizado';

        return $schema
            ->statePath('filtros')
            ->columns(['md' => 2, 'xl' => 4])
            ->components([
                Select::make('periodo')
                    ->label('Período')
                    ->options(fn (): array => Filtro::opcoesDePeriodo())
                    ->selectablePlaceholder(false)
                    ->live(),
                DatePicker::make('de')
                    ->label('De')
                    ->visible($personalizado)
                    ->live(),
                DatePicker::make('ate')
                    ->label('Até')
                    ->afterOrEqual('de')
                    ->visible($personalizado)
                    ->live(),
                Select::make('jogo')
                    ->options(fn (): array => Jogo::query()->reais()->where('status', '!=', StatusJogo::Rascunho)
                        ->orderByDesc('data')->get(['id', 'nome', 'data'])
                        ->mapWithKeys(fn (Jogo $jogo): array => [$jogo->id => $jogo->nome.($jogo->data ? ' · '.$jogo->data->format('d/m/Y') : '')])
                        ->all())
                    ->searchable()
                    ->placeholder('Todos')
                    ->live(),
                Select::make('jogador')
                    ->options(fn (): array => Usuario::query()->withTrashed()->whereHas('participacoes')->orderBy('nome')->get()
                        ->mapWithKeys(fn (Usuario $u): array => [$u->id => $u->nome.($u->apelido ? ' ('.$u->apelido.')' : '')])
                        ->all())
                    ->searchable()
                    ->placeholder('Todos')
                    ->live(),
            ]);
    }

    public function escolher(string $tipo): void
    {
        if (array_key_exists($tipo, GeradorDeRelatorios::TIPOS)) {
            $this->relatorio = $tipo;
        }
    }

    public function relatorioAtual(): Relatorio
    {
        return app(GeradorDeRelatorios::class)->gerar($this->relatorio, Filtro::doFormulario($this->filtros ?? []));
    }

    protected function getHeaderActions(): array
    {
        $podeExportar = fn (): bool => Filament::auth()->user()->can('relatorios.exportar');

        return [
            Action::make('exportarCsv')
                ->label('Exportar CSV')
                ->icon('rh-baixar')
                ->color('gray')
                ->visible($podeExportar)
                ->action(function (): StreamedResponse {
                    $relatorio = $this->relatorioAtual();
                    $csv = app(Exportacao::class)->csv($relatorio);

                    return response()->streamDownload(function () use ($csv): void {
                        echo $csv;
                    }, $relatorio->arquivo().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
            Action::make('exportarExcel')
                ->label('Exportar Excel')
                ->icon('rh-baixar')
                ->color('gray')
                ->visible($podeExportar)
                ->action(function (): StreamedResponse {
                    $relatorio = $this->relatorioAtual();
                    $caminho = tempnam(sys_get_temp_dir(), 'rh-relatorio');
                    app(Exportacao::class)->xlsx($relatorio, $caminho);
                    $conteudo = file_get_contents($caminho);
                    @unlink($caminho);

                    return response()->streamDownload(function () use ($conteudo): void {
                        echo $conteudo;
                    }, $relatorio->arquivo().'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
                }),
            // PDF = página para imprimir em outra aba, com o diálogo do navegador já aberto (Salvar como PDF).
            Action::make('exportarPdf')
                ->label('Exportar PDF')
                ->icon('rh-baixar')
                ->visible($podeExportar)
                ->url(fn (): string => $this->urlDeImpressao())
                ->openUrlInNewTab(),
        ];
    }

    /** Página para imprimir com o relatório e os filtros da tela. */
    public function urlDeImpressao(): string
    {
        $filtros = array_filter(
            array_intersect_key($this->filtros ?? [], array_flip(['periodo', 'de', 'ate', 'jogo', 'jogador'])),
            'filled',
        );

        return route('filament.painel.relatorios.imprimir', ['relatorio' => $this->relatorio, ...$filtros, 'imprimir' => 1]);
    }
}
