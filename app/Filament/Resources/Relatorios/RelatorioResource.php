<?php

namespace App\Filament\Resources\Relatorios;

use App\Filament\Resources\Relatorios\Pages\ListarRelatorios;
use App\Filament\Resources\Relatorios\Pages\VisualizarRelatorio;
use App\Filament\Resources\Relatorios\Schemas\DetalhesRelatorio;
use App\Filament\Resources\Relatorios\Tables\TabelaRelatorios;
use App\Models\Relatorio;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RelatorioResource extends Resource
{
    protected static ?string $model = Relatorio::class;

    protected static ?string $modelLabel = 'relatório';

    protected static ?string $pluralModelLabel = 'relatórios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function infolist(Schema $schema): Schema
    {
        return DetalhesRelatorio::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TabelaRelatorios::configure($table);
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
            'index' => ListarRelatorios::route('/'),
            'view' => VisualizarRelatorio::route('/{record}'),
        ];
    }
}
