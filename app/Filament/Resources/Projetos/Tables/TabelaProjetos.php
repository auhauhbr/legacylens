<?php

namespace App\Filament\Resources\Projetos\Tables;

use App\Dominio\Analises\Servicos\IniciadorAnalise;
use App\Models\Projeto;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TabelaProjetos
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                TextColumn::make('tipo')->label('Tipo')->badge(),
                TextColumn::make('tipo_origem')->label('Origem')->badge(),
                TextColumn::make('ramo')->label('Branch'),
                TextColumn::make('analises_count')->counts('analises')->label('Análises'),
                TextColumn::make('criado_em')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([])
            ->recordActions([
                Action::make('iniciar_analise')
                    ->label('Iniciar análise')
                    ->icon('heroicon-o-play')
                    ->requiresConfirmation()
                    ->modalHeading('Iniciar análise do projeto?')
                    ->modalDescription('A análise será adicionada à fila e não modificará o projeto cadastrado.')
                    ->disabled(fn (Projeto $record): bool => blank($record->caminho_local))
                    ->action(function (Projeto $record, IniciadorAnalise $iniciador): void {
                        $iniciador->iniciar($record);

                        Notification::make()
                            ->title('Análise adicionada à fila')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
