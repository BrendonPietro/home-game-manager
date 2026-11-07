<?php

namespace App\Filament\Schemas;

use App\Enums\StatusAluguel;
use App\Enums\TipoEvento;
use App\Models\Aluguel;
use App\Models\Evento;
use App\Models\Mesa;
use App\Models\Usuario;
use App\Services\Agenda\Conflitos;
use App\Support\Datas;
use App\Support\Dinheiro;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Tela 18: evento do calendário que não é jogo (aluguel de mesa, reserva, bloqueio). Usado no modal do
 * Calendário e nos Aluguéis; quem grava é o SalvarEvento. Avisa (sem impedir) quando a mesa já está
 * ocupada nesse horário.
 */
class EventoForm
{
    /** @param  bool  $soAluguel  tela de aluguéis: o tipo é sempre aluguel */
    public static function componentes(bool $soAluguel = false): array
    {
        $eAluguel = fn (Get $get): bool => self::tipo($get) === TipoEvento::Aluguel;

        return [
            $soAluguel ? Hidden::make('tipo')->default(TipoEvento::Aluguel->value) : ToggleButtons::make('tipo')
                ->label('Tipo de evento')
                ->options([
                    TipoEvento::Aluguel->value => 'Aluguel de mesa',
                    TipoEvento::Reserva->value => 'Reserva',
                    TipoEvento::Bloqueio->value => 'Bloqueio',
                ])
                ->default(TipoEvento::Aluguel->value)
                ->inline()
                ->required()
                ->live()
                ->helperText('Torneio ou cash game: crie em Jogos (ou a partir de um modelo); o jogo entra no calendário sozinho.')
                ->columnSpanFull(),

            TextInput::make('cliente')
                ->required()
                ->maxLength(255)
                ->placeholder('Felipe')
                ->helperText('No calendário: "Mesa alugada — Cliente".')
                ->visible($eAluguel)
                ->columnSpanFull(),
            TextInput::make('nome')
                ->required()
                ->maxLength(255)
                ->placeholder(fn (Get $get): string => self::tipo($get) === TipoEvento::Bloqueio ? 'Manutenção da Mesa 03' : 'Reserva — aniversário do Diego')
                ->visible(fn (Get $get): bool => ! $eAluguel($get))
                ->columnSpanFull(),

            Grid::make(3)->schema([
                DatePicker::make('data')
                    ->required()
                    ->live(),
                TimePicker::make('hora_inicio')
                    ->label('Horário inicial')
                    ->seconds(false)
                    ->helperText('Vazio: dia todo.')
                    ->live(),
                TimePicker::make('hora_fim')
                    ->label('Horário final')
                    ->seconds(false)
                    ->helperText('Pode passar da meia-noite.')
                    ->live(),
            ])->columnSpanFull(),

            Grid::make(2)->schema([
                Select::make('mesas')
                    ->multiple()
                    ->options(fn (): array => Mesa::query()->orderBy('nome')->get()
                        ->mapWithKeys(fn (Mesa $mesa): array => [$mesa->id => $mesa->nome.($mesa->ativa ? '' : ' (inativa)')])
                        ->all())
                    ->placeholder('Nenhuma')
                    ->live()
                    ->afterStateUpdated(function (?array $state, Get $get, Set $set): void {
                        // Aluguel ainda sem valor: sugere a soma do aluguel das mesas.
                        if (self::tipo($get) === TipoEvento::Aluguel && blank($get('valor'))) {
                            $soma = Mesa::query()->whereKey($state ?? [])->get()->sum(fn (Mesa $mesa): int => Dinheiro::centavos($mesa->valor_aluguel));
                            $set('valor', $soma > 0 ? Dinheiro::decimal($soma) : null);
                        }
                    }),
                Select::make('responsavel_id')
                    ->label('Responsável')
                    ->options(fn (): array => Usuario::query()->ativos()->orderBy('nome')->pluck('nome', 'id')->all())
                    ->searchable()
                    ->placeholder('Ninguém'),
            ])->columnSpanFull(),

            Grid::make(2)->schema([
                TextInput::make('valor')
                    ->label('Valor do aluguel')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('R$'),
                Select::make('status')
                    ->options(StatusAluguel::opcoes())
                    ->default(StatusAluguel::Reservado->value)
                    ->required()
                    ->selectablePlaceholder(false),
            ])->visible($eAluguel)->columnSpanFull(),

            Textarea::make('observacoes')
                ->label('Observações')
                ->rows(3)
                ->columnSpanFull(),

            Callout::make(fn (Get $get, ?Model $record): string => self::aviso($get, $record))
                ->status(fn (Get $get, ?Model $record): string => self::conflitos($get, $record)->isEmpty() ? 'info' : 'warning')
                ->visible(fn (Get $get): bool => filled($get('data')) && filled($get('mesas')))
                ->columnSpanFull(),
        ];
    }

    /** Campos do formulário a partir de um evento gravado. */
    public static function preencher(Evento $evento): array
    {
        return [
            'tipo' => $evento->tipo->value,
            'nome' => $evento->nome,
            'cliente' => $evento->aluguel?->cliente,
            'data' => $evento->data->toDateString(),
            'hora_inicio' => Datas::hora($evento->hora_inicio),
            'hora_fim' => Datas::hora($evento->hora_fim),
            'mesas' => $evento->mesas->modelKeys(),
            'responsavel_id' => $evento->responsavel_id,
            'valor' => $evento->aluguel?->valor,
            'status' => $evento->aluguel?->status->value ?? StatusAluguel::Reservado->value,
            'observacoes' => $evento->observacoes,
        ];
    }

    private static function tipo(Get $get): ?TipoEvento
    {
        $tipo = $get('tipo');

        return $tipo instanceof TipoEvento ? $tipo : TipoEvento::tryFrom((string) $tipo);
    }

    /** O registro do modal é o evento (calendário) ou o aluguel (tela de aluguéis). */
    private static function idDoEvento(?Model $record): ?int
    {
        return $record instanceof Aluguel ? $record->evento_id : $record?->getKey();
    }

    /** @return Collection<int, Evento> */
    private static function conflitos(Get $get, ?Model $record): Collection
    {
        return app(Conflitos::class)->de($get('data'), $get('hora_inicio'), $get('hora_fim'), $get('mesas') ?? [], self::idDoEvento($record));
    }

    /** "Mesa 02 está livre nesse horário." ou "Mesa 02 já tem Mesa alugada — Felipe (19:00–23:30) nesse horário." (o torneio não ocupa mesas do cadastro) */
    private static function aviso(Get $get, ?Model $record): string
    {
        $mesas = Mesa::query()->whereKey($get('mesas'))->orderBy('nome')->pluck('nome', 'id');
        $conflitos = self::conflitos($get, $record);

        if ($conflitos->isEmpty()) {
            return $mesas->join(', ', ' e ').($mesas->count() > 1 ? ' estão livres' : ' está livre').' nesse horário.';
        }

        return $conflitos
            ->map(fn (Evento $evento): string => $evento->mesas->whereIn('id', $mesas->keys())->sortBy('nome')->pluck('nome')->join(' e ')
                .' já tem '.$evento->nome.' ('.mb_strtolower($evento->horario()).')')
            ->join('; ').' nesse horário.';
    }
}
