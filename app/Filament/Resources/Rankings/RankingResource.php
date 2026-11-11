<?php

namespace App\Filament\Resources\Rankings;

use App\Filament\Resources\Rankings\Pages\ClassificacaoRanking;
use App\Filament\Resources\Rankings\Pages\CreateRanking;
use App\Filament\Resources\Rankings\Pages\EditRanking;
use App\Filament\Resources\Rankings\Pages\ListRankings;
use App\Filament\Resources\Rankings\Schemas\RankingForm;
use App\Filament\Resources\Rankings\Tables\RankingsTable;
use App\Models\Ranking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Rankings (docs/regras.md, "Rankings"): vários ao mesmo tempo, cada um com as posições que pontuam,
 * bônus de mesa final e de participação. O jogo escolhe em quais vale; os pontos são gravados ao
 * finalizar (Services\Ranking\Pontuacao). A classificação soma os pontos no período do ranking.
 */
class RankingResource extends Resource
{
    protected static ?string $model = Ranking::class;

    protected static string|BackedEnum|null $navigationIcon = 'rh-menu-rankings';

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = 'ranking';

    protected static ?string $pluralModelLabel = 'rankings';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return RankingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RankingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRankings::route('/'),
            'create' => CreateRanking::route('/create'),
            'edit' => EditRanking::route('/{record}/edit'),
            'classificacao' => ClassificacaoRanking::route('/{record}/classificacao'),
        ];
    }
}
