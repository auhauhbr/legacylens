<?php

namespace App\Filament\Resources\Projetos;

use App\Filament\Resources\Projetos\Pages\CriarProjeto;
use App\Filament\Resources\Projetos\Pages\EditarProjeto;
use App\Filament\Resources\Projetos\Pages\ListarProjetos;
use App\Filament\Resources\Projetos\Pages\VisualizarProjeto;
use App\Filament\Resources\Projetos\Schemas\DetalhesProjeto;
use App\Filament\Resources\Projetos\Schemas\FormularioProjeto;
use App\Filament\Resources\Projetos\Tables\TabelaProjetos;
use App\Models\Projeto;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjetoResource extends Resource
{
    protected static ?string $model = Projeto::class;

    protected static ?string $modelLabel = 'projeto';

    protected static ?string $pluralModelLabel = 'projetos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FormularioProjeto::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DetalhesProjeto::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TabelaProjetos::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('usuario_id', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListarProjetos::route('/'),
            'create' => CriarProjeto::route('/create'),
            'view' => VisualizarProjeto::route('/{record}'),
            'edit' => EditarProjeto::route('/{record}/edit'),
        ];
    }
}
