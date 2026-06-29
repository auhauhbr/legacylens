<?php

namespace App\Filament\Resources\Projetos\Pages;

use App\Filament\Resources\Projetos\ProjetoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditarProjeto extends EditRecord
{
    protected static string $resource = ProjetoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
