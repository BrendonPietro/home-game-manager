<?php

namespace App\Filament\Pages;

use App\Actions\Agenda\SalvarEvento;
use App\Enums\StatusAluguel;
use App\Enums\TipoEvento;
use App\Filament\Resources\Jogos\JogoResource;
use App\Filament\Schemas\EventoForm;
use App\Models\Evento;
use App\Models\Jogo;
use App\Support\EtapaDoJogo;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Tela 17: grade do mês com jogos, aluguéis de mesa, reservas e bloqueios. Clicar num dia vazio cria
 * evento nesse dia; clicar no evento edita (aluguel, reserva, bloqueio) ou mostra o resumo (jogo, ou
 * quem só pode ver). O evento do jogo vem do cadastro do jogo; aluguel cancelado sai da grade.
 */
class Calendario extends Page
{
    protected string $view = 'filament.painel.calendario';

    protected static string|BackedEnum|null $navigationIcon = 'rh-cal';

    protected static string|UnitEnum|null $navigationGroup = 'Agenda';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Calendário';

    protected static ?string $title = 'Calendário';

    protected static ?string $slug = 'calendario';

    /** Mês mostrado, "2026-10" */
    #[Url]
    public ?string $mes = null;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('viewAny', Evento::class) ?? false;
    }

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $this->mes)) {
            $this->mes = today()->format('Y-m');
        }
    }

    public function mudarMes(int $passo): void
    {
        $this->mes = $this->inicioDoMes()->addMonthsNoOverflow($passo)->format('Y-m');
    }

    public function hoje(): void
    {
        $this->mes = today()->format('Y-m');
    }

    public function inicioDoMes(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->mes);
    }

    /** "Outubro 2026" */
    public function nomeDoMes(): string
    {
        return Str::ucfirst($this->inicioDoMes()->translatedFormat('F Y'));
    }

    /**
     * Semanas de domingo a sábado cobrindo o mês, com os eventos de cada dia (dia todo primeiro).
     *
     * @return list<list<array{data: CarbonImmutable, fora: bool, hoje: bool, eventos: Collection<int, Evento>}>>
     */
    public function semanas(): array
    {
        $inicio = $this->inicioDoMes()->startOfWeek(CarbonInterface::SUNDAY);
        $fim = $this->inicioDoMes()->endOfMonth()->endOfWeek(CarbonInterface::SATURDAY);

        $eventos = Evento::query()
            ->with(['mesas', 'aluguel', 'jogo'])
            ->whereDate('data', '>=', $inicio)
            ->whereDate('data', '<=', $fim)
            ->whereDoesntHave('aluguel', fn (Builder $q) => $q->where('status', StatusAluguel::Cancelado))
            ->orderBy('data')
            ->orderByRaw('hora_inicio is not null')
            ->orderBy('hora_inicio')
            ->get()
            ->groupBy(fn (Evento $evento): string => $evento->data->toDateString());

        $semanas = [];
        for ($dia = $inicio; $dia <= $fim; $dia = $dia->addDay()) {
            $semanas[intdiv($inicio->diffInDays($dia), 7)][] = [
                'data' => $dia,
                'fora' => $dia->month !== $this->inicioDoMes()->month,
                'hoje' => $dia->isToday(),
                'eventos' => $eventos->get($dia->toDateString(), collect()),
            ];
        }

        return $semanas;
    }

    /** Ação ao clicar no evento: editar (quem pode e não é jogo) ou ver o resumo. */
    public function acaoDoEvento(Evento $evento): string
    {
        return Filament::auth()->user()->can('update', $evento) ? 'editarEvento' : 'verEvento';
    }

    public function podeCriar(): bool
    {
        return Filament::auth()->user()->can('create', Evento::class);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('novoJogo')
                ->label('Novo jogo')
                ->icon('rh-trophy')
                ->color('gray')
                ->url(fn (): string => JogoResource::getUrl('create'))
                ->visible(fn (): bool => Filament::auth()->user()->can('create', Jogo::class)),
            $this->novoEventoAction(),
        ];
    }

    public function novoEventoAction(): Action
    {
        return Action::make('novoEvento')
            ->label('Novo evento')
            ->icon('rh-plus')
            ->modalHeading('Novo evento')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Salvar evento')
            ->visible(fn (): bool => $this->podeCriar())
            ->fillForm(fn (array $arguments): array => [
                'tipo' => TipoEvento::Aluguel->value,
                'status' => StatusAluguel::Reservado->value,
                'data' => $arguments['data'] ?? null,
            ])
            ->schema(EventoForm::componentes())
            ->action(function (array $data): void {
                app(SalvarEvento::class)(null, $data);
                Notification::make()->success()->title('Evento salvo.')->send();
            });
    }

    public function editarEventoAction(): Action
    {
        return Action::make('editarEvento')
            ->record(fn (array $arguments): ?Evento => Evento::query()->with(['mesas', 'aluguel'])->find($arguments['evento'] ?? null))
            ->authorize('update')
            ->modalHeading(fn (Evento $record): string => $record->nome)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Salvar evento')
            ->fillForm(fn (Evento $record): array => EventoForm::preencher($record))
            ->schema(EventoForm::componentes())
            ->action(function (array $data, Evento $record): void {
                app(SalvarEvento::class)($record, $data);
                Notification::make()->success()->title('Evento salvo.')->send();
            })
            ->extraModalFooterActions(fn (Evento $record): array => [
                Action::make('excluirEvento')
                    ->label('Excluir')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Excluir '.$record->nome.'?')
                    ->modalDescription('O evento sai do calendário'.($record->tipo === TipoEvento::Aluguel ? ' e da lista de aluguéis.' : '.'))
                    ->modalSubmitActionLabel('Excluir')
                    ->visible(fn (): bool => Filament::auth()->user()->can('delete', $record))
                    ->action(function () use ($record): void {
                        $record->delete();
                        Notification::make()->success()->title('Evento excluído.')->send();
                    })
                    ->cancelParentActions(),
            ]);
    }

    public function verEventoAction(): Action
    {
        return Action::make('verEvento')
            ->record(fn (array $arguments): ?Evento => Evento::query()
                ->with(['mesas', 'aluguel', 'responsavel', 'jogo' => fn ($q) => $q->withCount('participantes')->with('itens')])
                ->find($arguments['evento'] ?? null))
            ->authorize('view')
            ->modalHeading(fn (Evento $record): string => $record->nome)
            ->modalWidth(Width::Medium)
            ->modalContent(fn (Evento $record) => view('filament.painel.evento-resumo', ['evento' => $record]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->extraModalFooterActions(fn (Evento $record): array => $record->jogo ? [
                Action::make('editarJogo')
                    ->label('Ver no painel')
                    ->color('gray')
                    ->url(JogoResource::getUrl('view', ['record' => $record->jogo])),
                Action::make('abrirJogo')
                    ->label('Abrir no site')
                    ->url(EtapaDoJogo::url($record->jogo))
                    ->openUrlInNewTab()
                    ->visible(EtapaDoJogo::url($record->jogo) !== null),
            ] : []);
    }
}
