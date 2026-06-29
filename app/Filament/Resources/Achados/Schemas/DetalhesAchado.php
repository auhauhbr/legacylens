<?php

namespace App\Filament\Resources\Achados\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DetalhesAchado
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('analise.projeto.nome')->label('Projeto'),
                TextEntry::make('codigo')->label('Código'),
                TextEntry::make('categoria')->label('Categoria')->badge(),
                TextEntry::make('severidade')->label('Severidade')->badge(),
                TextEntry::make('titulo')->label('Título')->columnSpanFull(),
                TextEntry::make('descricao')->label('Descrição')->columnSpanFull(),
                TextEntry::make('recomendacao')->label('Recomendação')->columnSpanFull(),
                TextEntry::make('caminho_arquivo')->label('Arquivo')->columnSpanFull(),
                TextEntry::make('evidencia')->label('Evidência')->formatStateUsing(
                    fn (mixed $estado): string => json_encode($estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: ''
                )->columnSpanFull(),
            ]);
    }
}
