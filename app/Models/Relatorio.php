<?php

namespace App\Models;

use App\Enums\TipoRelatorio;
use Database\Factories\RelatorioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relatorio extends Modelo
{
    /** @use HasFactory<RelatorioFactory> */
    use HasFactory;

    protected $table = 'relatorios';

    protected $fillable = [
        'analise_id', 'tipo', 'titulo', 'conteudo_markdown',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoRelatorio::class,
        ];
    }

    public function analise(): BelongsTo
    {
        return $this->belongsTo(Analise::class, 'analise_id');
    }
}
