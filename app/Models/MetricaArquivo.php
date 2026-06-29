<?php

namespace App\Models;

use Database\Factories\MetricaArquivoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricaArquivo extends Modelo
{
    /** @use HasFactory<MetricaArquivoFactory> */
    use HasFactory;

    protected $table = 'metricas_arquivos';

    protected $fillable = [
        'analise_id', 'caminho_arquivo', 'tipo_arquivo', 'total_linhas',
        'total_metodos', 'total_classes', 'complexidade_estimada', 'controlador',
        'modelo', 'servico',
    ];

    protected function casts(): array
    {
        return [
            'controlador' => 'boolean',
            'modelo' => 'boolean',
            'servico' => 'boolean',
        ];
    }

    public function analise(): BelongsTo
    {
        return $this->belongsTo(Analise::class, 'analise_id');
    }
}
