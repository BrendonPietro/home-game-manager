<?php

namespace App\Filament\Resources\Alugueis;

use App\Actions\Agenda\SalvarEvento;
use App\Enums\StatusAluguel;
use App\Enums\TipoEvento;
use App\Filament\Resources\Alugueis\Pages\ListAlugueis;
use App\Filament\Resources\Alugueis\Widgets\TotaisDosAlugueis;
use App\Filament\Schemas\EventoForm;
use App\Models\Aluguel;
use App\Models\Evento;
use App\Support\Dinheiro;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tela 23: aluguéis de mesa. Cada aluguel é um evento do calendário (tipo aluguel) com cliente, valor e
 * status; criar e editar usam o mesmo formulário do calendário (EventoForm) e o SalvarEvento.
 */
class AluguelResource extends Resource
{
    protected static ?string $model = Aluguel::class;

    protected static string|BackedEnum|null $navigationIcon = 'rh-key';

    protected static ?int $navigationSort = 80;

    protected static ?string $modelLabel = 'aluguel';

    protected static ?string $pluralModelLabel = 'aluguéis';

    protected static ?string $slug = 'alugueis';

    protected static ?string $recordTitleAttribute = 'cliente';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('evento.mesas');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc(self::dataDoEvento()))
            ->columns([
                TextColumn::make('cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mesas')
                    ->state(fn (Aluguel $aluguel): string => $aluguel->evento->nomesDasMesas()),
                TextColumn::make('evento.data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(self::dataDoEvento(), $direction)),
                TextColumn::make('horario')
                    ->label('Horário')
                    ->state(fn (Aluguel $aluguel): string => $aluguel->evento->horario())
                    ->color('gray'),
                TextColumn::make('valor')
                    ->formatStateUsing(fn (?string $state): string => Dinheiro::brl($state))
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (StatusAluguel $state): string => $state->label())
                    ->color(fn (StatusAluguel $state): string => self::corDoStatus($state)),
            ])
            ->filters([
                Filter::make('periodo')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('de')->label('De'),
                        DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => self::noPeriodo($query, $data))
                    ->indicateUsing(function (array $data): array {
                        $indicadores = [];
                        foreach (['de' => 'De', 'ate' => 'Até'] as $campo => $rotulo) {
                            if (filled($data[$campo] ?? null)) {
                                $indicadores[] = Indicator::make($rotulo.' '.date('d/m/Y', strtotime($data[$campo])))->removeField($campo);
                            }
                        }

                        return $indicadores;
                    }),
            ])
            ->recordActions([
                Action::make('marcarPago')
                    ->label('Marcar pago')
                    ->icon('rh-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Aluguel $record): string => 'Aluguel de '.$record->cliente.' pago?')
                    ->modalDescription(fn (Aluguel $record): string => Dinheiro::brl($record->valor).' · '.$record->evento->data->format('d/m/Y'))
                    ->modalSubmitActionLabel('Marcar pago')
                    ->authorize('update')
                    ->visible(fn (Aluguel $record): bool => $record->emAberto())
                    ->action(function (Aluguel $record): void {
                        $record->update(['status' => StatusAluguel::Pago, 'pago_em' => now()]);
                        Notification::make()->success()->title('Aluguel pago.')->send();
                    }),
                self::acaoEditar(),
                DeleteAction::make()
                    ->modalDescription('O aluguel sai da lista e do calendário.')
                    ->using(fn (Aluguel $record) => $record->evento->delete()),
            ]);
    }

    /** Novo aluguel: o formulário do calendário com o tipo fixo em aluguel. */
    public static function acaoNovo(): Action
    {
        return Action::make('novoAluguel')
            ->label('Novo aluguel')
            ->icon('rh-plus')
            ->authorize('create')
            ->modalHeading('Novo aluguel')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Salvar aluguel')
            ->fillForm(['tipo' => TipoEvento::Aluguel->value, 'status' => StatusAluguel::Reservado->value])
            ->schema(EventoForm::componentes(soAluguel: true))
            ->action(function (array $data): void {
                app(SalvarEvento::class)(null, ['tipo' => TipoEvento::Aluguel] + $data);
                Notification::make()->success()->title('Aluguel salvo.')->send();
            });
    }

    public static function acaoEditar(): Action
    {
        return Action::make('editar')
            ->label('Editar')
            ->icon('rh-edit')
            ->color('gray')
            ->authorize('update')
            ->modalHeading(fn (Aluguel $record): string => 'Aluguel de '.$record->cliente)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Salvar aluguel')
            ->fillForm(fn (Aluguel $record): array => EventoForm::preencher($record->evento))
            ->schema(EventoForm::componentes(soAluguel: true))
            ->action(function (array $data, Aluguel $record): void {
                app(SalvarEvento::class)($record->evento, ['tipo' => TipoEvento::Aluguel] + $data);
                Notification::make()->success()->title('Aluguel salvo.')->send();
            });
    }

    /** Aluguéis cujo evento cai no período (datas "Y-m-d", qualquer uma vazia). */
    public static function noPeriodo(Builder $query, array $periodo): Builder
    {
        return $query->whereHas('evento', fn (Builder $evento) => $evento
            ->when($periodo['de'] ?? null, fn (Builder $q, string $de) => $q->whereDate('data', '>=', $de))
            ->when($periodo['ate'] ?? null, fn (Builder $q, string $ate) => $q->whereDate('data', '<=', $ate)));
    }

    public static function corDoStatus(StatusAluguel $status): string
    {
        return match ($status) {
            StatusAluguel::Reservado => 'warning',
            StatusAluguel::Confirmado => 'info',
            StatusAluguel::Pago => 'success',
            StatusAluguel::Cancelado => 'gray',
        };
    }

    private static function dataDoEvento(): Builder
    {
        return Evento::query()->select('data')->whereColumn('eventos.id', 'alugueis.evento_id');
    }

    public static function getWidgets(): array
    {
        return [
            TotaisDosAlugueis::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAlugueis::route('/'),
        ];
    }
}
