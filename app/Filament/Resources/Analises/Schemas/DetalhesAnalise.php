<?php

namespace App\Filament\Resources\Analises\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DetalhesAnalise
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('projeto.nome')->label('Projeto'),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('pontuacao')->label('Pontuação'),
                TextEntry::make('nivel_risco')->label('Nível de risco')->badge(),
                TextEntry::make('iniciado_em')->label('Iniciada em')->dateTime('d/m/Y H:i'),
                TextEntry::make('finalizado_em')->label('Finalizada em')->dateTime('d/m/Y H:i'),
                TextEntry::make('duracao_segundos')->label('Duração em segundos'),
                TextEntry::make('resumo')->label('Resumo')->columnSpanFull(),
                TextEntry::make('mensagem_erro')->label('Mensagem de erro')->columnSpanFull(),
            ]);
    }
}
