<?php

namespace App\Filament\Resources\Mesas;

use App\Filament\Resources\Mesas\Pages\ManageMesas;
use App\Models\Mesa;
use App\Support\Dinheiro;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Tela 22: mesas físicas do clube, para aluguéis e reservas (o torneio tem as mesas dele). Mesa com evento
 * na agenda não se exclui (MesaPolicy): desative.
 */
class MesaResource extends Resource
{
    protected static ?string $model = Mesa::class;

    protected static string|BackedEnum|null $navigationIcon = 'rh-menu-mesas';

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'mesa';

    protected static ?string $pluralModelLabel = 'mesas';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->maxLength(60)
                    ->unique(ignoreRecord: true)
                    ->placeholder('Mesa 01'),
                TextInput::make('lugares')
                    ->required()
                    ->integer()
                    ->minValue(2)
                    ->maxValue(12)
                    ->default(9)
                    ->helperText('Sem contar o dealer.'),
                TextInput::make('valor_aluguel')
                    ->label('Valor do aluguel')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('R$'),
                Toggle::make('ativa')
                    ->default(true)
                    ->helperText('Mesa inativa não aparece para aluguéis e reservas.')
                    ->inline(false),
                Textarea::make('observacoes')
                    ->label('Observações')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('lugares')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('valor_aluguel')
                    ->label('Aluguel')
                    ->formatStateUsing(fn (?string $state) => Dinheiro::brl($state))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('eventos_count')
                    ->label('Na agenda')
                    ->counts('eventos')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('ativa')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('ativa'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMesas::route('/'),
        ];
    }
}
