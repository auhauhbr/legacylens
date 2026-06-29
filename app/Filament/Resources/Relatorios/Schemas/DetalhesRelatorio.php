<?php

namespace App\Filament\Resources\Relatorios\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DetalhesRelatorio
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('analise.projeto.nome')->label('Projeto'),
                TextEntry::make('tipo')->label('Tipo')->badge(),
                TextEntry::make('titulo')->label('Título')->columnSpanFull(),
                TextEntry::make('conteudo_markdown')
                    ->label('Conteúdo')
                    ->markdown()
                    ->columnSpanFull(),
            ]);
    }
}
