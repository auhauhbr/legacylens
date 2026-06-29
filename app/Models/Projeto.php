<?php

namespace App\Models;

use App\Enums\TipoOrigemProjeto;
use App\Enums\TipoProjeto;
use Database\Factories\ProjetoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Projeto extends Modelo
{
    /** @use HasFactory<ProjetoFactory> */
    use HasFactory;

    protected $table = 'projetos';

    protected $fillable = [
        'usuario_id', 'nome', 'descricao', 'tipo', 'tipo_origem', 'caminho_local',
        'url_repositorio', 'ramo', 'configuracao_padrao_analise',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoProjeto::class,
            'tipo_origem' => TipoOrigemProjeto::class,
            'caminho_local' => 'encrypted',
            'configuracao_padrao_analise' => 'array',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function analises(): HasMany
    {
        return $this->hasMany(Analise::class, 'projeto_id');
    }
}
