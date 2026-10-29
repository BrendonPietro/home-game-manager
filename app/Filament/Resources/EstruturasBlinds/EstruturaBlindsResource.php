<?php

namespace App\Filament\Resources\EstruturasBlinds;

use App\Filament\Resources\EstruturasBlinds\Pages\CreateEstruturaBlinds;
use App\Filament\Resources\EstruturasBlinds\Pages\EditEstruturaBlinds;
use App\Filament\Resources\EstruturasBlinds\Pages\ListEstruturasBlinds;
use App\Filament\Resources\EstruturasBlinds\Schemas\EstruturaBlindsForm;
use App\Filament\Resources\EstruturasBlinds\Tables\EstruturasBlindsTable;
use App\Models\EstruturaBlinds;
use BackedEnum;
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

    protected static ?string $modelLabel = 'estrutura de blinds';

    protected static ?string $pluralModelLabel = 'estruturas de blinds';

    protected static ?string $navigationLabel = 'Estruturas de blinds';

    protected static ?string $recordTitleAttribute = 'nome';

    // Título e breadcrumb "Estruturas de blinds", não "Estruturas De Blinds".
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
