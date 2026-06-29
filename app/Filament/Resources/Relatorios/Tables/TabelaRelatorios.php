<?php

namespace App\Filament\Resources\Relatorios\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TabelaRelatorios
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('analise.projeto.nome')->label('Projeto')->searchable(),
                TextColumn::make('tipo')->label('Tipo')->badge()->sortable(),
                TextColumn::make('titulo')->label('Título')->searchable(),
                TextColumn::make('criado_em')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('tipo')->label('Tipo')->options([
                    'executivo' => 'Executivo',
                    'tecnico' => 'Técnico',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
