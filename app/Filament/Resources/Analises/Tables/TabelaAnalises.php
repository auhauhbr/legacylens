<?php

namespace App\Filament\Resources\Analises\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TabelaAnalises
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('projeto.nome')->label('Projeto')->searchable()->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('pontuacao')->label('Pontuação')->sortable(),
                TextColumn::make('nivel_risco')->label('Risco')->badge(),
                TextColumn::make('duracao_segundos')->label('Duração (s)'),
                TextColumn::make('finalizado_em')->label('Finalizada em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options([
                    'pendente' => 'Pendente',
                    'em_execucao' => 'Em execução',
                    'concluida' => 'Concluída',
                    'falhou' => 'Falhou',
                    'cancelada' => 'Cancelada',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
