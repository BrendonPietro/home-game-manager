<?php

namespace App\Filament\Resources\Jogos;

use App\Filament\Resources\Jogos\Pages\CreateJogo;
use App\Filament\Resources\Jogos\Pages\EditJogo;
use App\Filament\Resources\Jogos\Pages\ListJogos;
use App\Filament\Resources\Jogos\Pages\ViewJogo;
use App\Filament\Resources\Jogos\Schemas\JogoForm;
use App\Filament\Resources\Jogos\Tables\JogosTable;
use App\Models\Jogo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Telas 19 e 20: jogos de verdade (torneio e cash game). Torneios modelo ficam no ModeloResource (fase 5).
 * Finalizado e cancelado abrem só para ver (o formulário desabilitado).
 */
class JogoResource extends Resource
{
    protected static ?string $model = Jogo::class;

    protected static string|BackedEnum|null $navigationIcon = 'rh-trophy';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'jogo';

    protected static ?string $pluralModelLabel = 'jogos';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->reais();
    }

    public static function form(Schema $schema): Schema
    {
        return JogoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JogosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJogos::route('/'),
            'create' => CreateJogo::route('/create'),
            'view' => ViewJogo::route('/{record}'),
            'edit' => EditJogo::route('/{record}/edit'),
        ];
    }
}
