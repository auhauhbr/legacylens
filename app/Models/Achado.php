<?php

namespace App\Models;

use App\Enums\CategoriaAchado;
use App\Enums\NivelConfianca;
use App\Enums\SeveridadeAchado;
use Database\Factories\AchadoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achado extends Modelo
{
    /** @use HasFactory<AchadoFactory> */
    use HasFactory;

    protected $table = 'achados';

    protected $fillable = [
        'analise_id', 'codigo', 'categoria', 'severidade', 'confianca', 'titulo',
        'descricao', 'recomendacao', 'caminho_arquivo', 'linha_inicial', 'linha_final',
        'evidencia', 'metadados', 'impressao_digital',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaAchado::class,
            'severidade' => SeveridadeAchado::class,
            'confianca' => NivelConfianca::class,
            'evidencia' => 'array',
            'metadados' => 'array',
        ];
    }

    public function analise(): BelongsTo
    {
        return $this->belongsTo(Analise::class, 'analise_id');
    }

    public function rascunhosIssuesGithub(): HasMany
    {
        return $this->hasMany(RascunhoIssueGithub::class, 'achado_id');
    }
}
