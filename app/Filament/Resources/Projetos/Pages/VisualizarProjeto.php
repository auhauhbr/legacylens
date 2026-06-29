<?php

namespace App\Filament\Resources\Projetos\Pages;

use App\Filament\Resources\Projetos\ProjetoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class VisualizarProjeto extends ViewRecord
{
    protected static string $resource = ProjetoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
