<?php

namespace App\Filament\Resources\Projetos\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FormularioProjeto
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                Textarea::make('descricao')
                    ->label('Descrição')
                    ->columnSpanFull(),
                Select::make('tipo')
                    ->label('Tipo de projeto')
                    ->options(['laravel' => 'Laravel', 'php' => 'PHP'])
                    ->required(),
                Select::make('tipo_origem')
                    ->label('Tipo de origem')
                    ->options(['local' => 'Caminho local'])
                    ->required(),
                TextInput::make('caminho_local')
                    ->label('Caminho local')
                    ->helperText('O LegacyLens nunca modifica o projeto informado.')
                    ->columnSpanFull(),
                TextInput::make('url_repositorio')
                    ->label('URL do repositório')
                    ->url()
                    ->columnSpanFull(),
                TextInput::make('ramo')
                    ->label('Branch'),
                KeyValue::make('configuracao_padrao_analise')
                    ->label('Configuração padrão da análise')
                    ->keyLabel('Opção')
                    ->valueLabel('Valor')
                    ->columnSpanFull(),
            ]);
    }
}
