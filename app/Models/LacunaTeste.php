<?php

namespace App\Models;

use Database\Factories\LacunaTesteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LacunaTeste extends Modelo
{
    /** @use HasFactory<LacunaTesteFactory> */
    use HasFactory;

    protected $table = 'lacunas_testes';

    protected $fillable = [
        'analise_id', 'tipo_alvo', 'nome_alvo', 'caminho_alvo',
        'caminho_teste_esperado', 'confianca',
    ];

    protected function casts(): array
    {
        return ['confianca' => 'integer'];
    }

    public function analise(): BelongsTo
    {
        return $this->belongsTo(Analise::class, 'analise_id');
    }
}
