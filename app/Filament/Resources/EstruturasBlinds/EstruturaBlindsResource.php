<?php

namespace App\Filament\Resources\EstruturasBlinds;

use App\Filament\Resources\EstruturasBlinds\Pages\CreateEstruturaBlinds;
use App\Filament\Resources\EstruturasBlinds\Pages\EditEstruturaBlinds;
use App\Filament\Resources\EstruturasBlinds\Pages\ListEstruturasBlinds;
use App\Filament\Resources\EstruturasBlinds\Schemas\EstruturaBlindsForm;
use App\Filament\Resources\EstruturasBlinds\Tables\EstruturasBlindsTable;
use App\Models\EstruturaBlinds;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Estruturas de blinds (v2, docs/regras.md "Blinds e relógio"): níveis com small, big, ante e duração,
 * e intervalos. O torneio escolhe a estrutura na aba Blinds do cadastro; o relógio da noite segue ela.
 */
class EstruturaBlindsResource extends Resource
{
    protected static ?string $model = EstruturaBlinds::class;

    protected static ?string $slug = 'estruturas';

    protected static string|BackedEnum|null $navigationIcon = 'rh-coins';

    protected static ?int $navigationSort = 45;

    // "Blinds" no menu e na lista; o que roda no torneio é o relógio.
    protected static ?string $modelLabel = 'estrutura de blinds';

    protected static ?string $pluralModelLabel = 'blinds';

    protected static ?string $navigationLabel = 'Blinds';

    protected static ?string $recordTitleAttribute = 'nome';

    /** Cópia com os mesmos níveis (lista e tela de edição), aberta para editar. */
    public static function duplicar(): Action
    {
        return Action::make('duplicar')
            ->label('Duplicar')
            ->icon('rh-copy')
            ->color('gray')
            ->visible(fn (): bool => static::canCreate())
            ->action(function (EstruturaBlinds $record, Action $action): void {
                $copia = $record->duplicar();

                Notification::make()->success()->title("Cópia criada: {$copia->nome}")->send();
                $action->redirect(static::getUrl('edit', ['record' => $copia]));
            });
    }

    // Título e breadcrumb "Estrutura de blinds", não "Estrutura De Blinds".
    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }

    public static function form(Schema $schema): Schema
    {
        return EstruturaBlindsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EstruturasBlindsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEstruturasBlinds::route('/'),
            'create' => CreateEstruturaBlinds::route('/create'),
            'edit' => EditEstruturaBlinds::route('/{record}/edit'),
        ];
    }
}
