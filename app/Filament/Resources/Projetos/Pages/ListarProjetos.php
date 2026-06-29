<?php

namespace App\Filament\Resources\Projetos\Pages;

use App\Filament\Resources\Projetos\ProjetoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListarProjetos extends ListRecords
{
    protected static string $resource = ProjetoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
