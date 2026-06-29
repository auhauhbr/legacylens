<?php

namespace App\Filament\Resources\Projetos\Pages;

use App\Filament\Resources\Projetos\ProjetoResource;
use Filament\Resources\Pages\CreateRecord;

class CriarProjeto extends CreateRecord
{
    protected static string $resource = ProjetoResource::class;

    protected function mutateFormDataBeforeCreate(array $dados): array
    {
        $dados['usuario_id'] = auth()->id();

        return $dados;
    }
}
