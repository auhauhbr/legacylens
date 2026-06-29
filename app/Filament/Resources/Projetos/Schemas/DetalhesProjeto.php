<?php

namespace App\Filament\Resources\Projetos\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DetalhesProjeto
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('nome')->label('Nome'),
                TextEntry::make('descricao')->label('Descrição')->columnSpanFull(),
                TextEntry::make('tipo')->label('Tipo')->badge(),
                TextEntry::make('tipo_origem')->label('Origem')->badge(),
                TextEntry::make('caminho_local')->label('Caminho local')->columnSpanFull(),
                TextEntry::make('url_repositorio')->label('Repositório')->columnSpanFull(),
                TextEntry::make('ramo')->label('Branch'),
                TextEntry::make('criado_em')->label('Criado em')->dateTime('d/m/Y H:i'),
            ]);
    }
}
