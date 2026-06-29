<?php

namespace App\Filament\Resources\Achados\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TabelaAchados
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('analise.projeto.nome')->label('Projeto')->searchable(),
                TextColumn::make('titulo')->label('Título')->searchable()->limit(60),
                TextColumn::make('categoria')->label('Categoria')->badge()->sortable(),
                TextColumn::make('severidade')->label('Severidade')->badge()->sortable(),
                TextColumn::make('caminho_arquivo')->label('Arquivo')->limit(50),
            ])
            ->filters([
                SelectFilter::make('severidade')->label('Severidade')->options([
                    'critica' => 'Crítica',
                    'alta' => 'Alta',
                    'media' => 'Média',
                    'baixa' => 'Baixa',
                    'informativa' => 'Informativa',
                ]),
                SelectFilter::make('categoria')->label('Categoria')->options([
                    'dependencias' => 'Dependências',
                    'seguranca' => 'Segurança',
                    'testes' => 'Testes',
                    'arquitetura' => 'Arquitetura',
                    'rotas' => 'Rotas',
                    'documentacao' => 'Documentação',
                    'integracao_continua' => 'Integração contínua',
                    'estilo' => 'Estilo',
                    'desempenho' => 'Desempenho',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
