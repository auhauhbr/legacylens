<?php

namespace App\Models;

use Database\Factories\EtapaModernizacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtapaModernizacao extends Modelo
{
    /** @use HasFactory<EtapaModernizacaoFactory> */
    use HasFactory;

    protected $table = 'etapas_modernizacao';

    protected $fillable = [
        'analise_id', 'fase', 'posicao', 'prioridade', 'titulo', 'descricao',
        'esforco', 'risco', 'criterios_aceite', 'ids_achados_relacionados',
    ];

    protected function casts(): array
    {
        return [
            'criterios_aceite' => 'array',
            'ids_achados_relacionados' => 'array',
        ];
    }

    public function analise(): BelongsTo
    {
        return $this->belongsTo(Analise::class, 'analise_id');
    }

    public function rascunhosIssuesGithub(): HasMany
    {
        return $this->hasMany(RascunhoIssueGithub::class, 'etapa_modernizacao_id');
    }
}
