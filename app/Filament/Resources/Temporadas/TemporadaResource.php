<?php

namespace App\Filament\Resources\Temporadas;

use App\Filament\Resources\Temporadas\Pages\ManageTemporadas;
use App\Models\Temporada;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Temporadas ("2026/2"): o jogo escolhe a sua no cadastro. Temporada com jogo não se exclui.
 *
 * POR ENQUANTO FORA DO PAINEL (NO_PAINEL = false): sem item no menu nem atalho, e sem o campo "Temporada" no
 * cadastro do jogo. O resto continua (tabela, model, períodos dos relatórios quando houver temporada):
 * para voltar, é só trocar para true.
 */
class TemporadaResource extends Resource
{
    public const NO_PAINEL = false;

    protected static ?string $model = Temporada::class;

    public static function shouldRegisterNavigation(): bool
    {
        return self::NO_PAINEL && parent::shouldRegisterNavigation();
    }

    protected static string|BackedEnum|null $navigationIcon = 'rh-menu-temporadas';

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 70;

    protected static ?string $modelLabel = 'temporada';

    protected static ?string $pluralModelLabel = 'temporadas';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(60)
                    ->unique(ignoreRecord: true)
                    ->placeholder('2026/2')
                    ->columnSpanFull(),
                DatePicker::make('inicio')->label('Começa em'),
                DatePicker::make('fim')->label('Termina em')->afterOrEqual('inicio'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('inicio', 'desc')
            ->columns([
                TextColumn::make('nome')->searchable()->sortable(),
                TextColumn::make('inicio')->label('Começa em')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('fim')->label('Termina em')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('jogos_count')->label('Jogos')->counts('jogos')->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTemporadas::route('/'),
        ];
    }
}
