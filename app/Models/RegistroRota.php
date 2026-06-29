<?php

namespace App\Models;

use Database\Factories\RegistroRotaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroRota extends Modelo
{
    /** @use HasFactory<RegistroRotaFactory> */
    use HasFactory;

    protected $table = 'registros_rotas';

    protected $fillable = [
        'analise_id', 'metodo', 'uri', 'nome', 'controlador', 'acao', 'middlewares',
        'arquivo_origem', 'metadados',
    ];

    protected function casts(): array
    {
        return [
            'middlewares' => 'array',
            'metadados' => 'array',
        ];
    }

    public function analise(): BelongsTo
    {
        return $this->belongsTo(Analise::class, 'analise_id');
    }
}
