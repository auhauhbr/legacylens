<?php

namespace App\Filament\Resources\Achados;

use App\Filament\Resources\Achados\Pages\ListarAchados;
use App\Filament\Resources\Achados\Pages\VisualizarAchado;
use App\Filament\Resources\Achados\Schemas\DetalhesAchado;
use App\Filament\Resources\Achados\Tables\TabelaAchados;
use App\Models\Achado;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AchadoResource extends Resource
{
    protected static ?string $model = Achado::class;

    protected static ?string $modelLabel = 'achado';

    protected static ?string $pluralModelLabel = 'achados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function infolist(Schema $schema): Schema
    {
        return DetalhesAchado::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TabelaAchados::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('analise.projeto', fn (Builder $consulta) => $consulta->where('usuario_id', auth()->id()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarAchados::route('/'),
            'view' => VisualizarAchado::route('/{record}'),
        ];
    }
}
