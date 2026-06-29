<?php

namespace App\Filament\Resources\Analises;

use App\Filament\Resources\Analises\Pages\ListarAnalises;
use App\Filament\Resources\Analises\Pages\VisualizarAnalise;
use App\Filament\Resources\Analises\Schemas\DetalhesAnalise;
use App\Filament\Resources\Analises\Tables\TabelaAnalises;
use App\Models\Analise;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnaliseResource extends Resource
{
    protected static ?string $model = Analise::class;

    protected static ?string $modelLabel = 'análise';

    protected static ?string $pluralModelLabel = 'análises';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function infolist(Schema $schema): Schema
    {
        return DetalhesAnalise::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TabelaAnalises::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('projeto', fn (Builder $consulta) => $consulta->where('usuario_id', auth()->id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarAnalises::route('/'),
            'view' => VisualizarAnalise::route('/{record}'),
        ];
    }
}
