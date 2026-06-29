<?php

namespace App\Models;

use Database\Factories\DependenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dependencia extends Modelo
{
    /** @use HasFactory<DependenciaFactory> */
    use HasFactory;

    protected $table = 'dependencias';

    protected $fillable = [
        'analise_id', 'nome_pacote', 'versao_atual', 'versao_mais_recente',
        'restricao', 'escopo', 'direta', 'desenvolvimento', 'abandonada',
        'possui_alerta_seguranca', 'metadados',
    ];

    protected function casts(): array
    {
        return [
            'direta' => 'boolean',
            'desenvolvimento' => 'boolean',
            'abandonada' => 'boolean',
            'possui_alerta_seguranca' => 'boolean',
            'metadados' => 'array',
        ];
    }

    public function analise(): BelongsTo
    {
        return $this->belongsTo(Analise::class, 'analise_id');
    }
}
