<?php

namespace App\Models;

use App\Enums\NivelRisco;
use App\Enums\StatusAnalise;
use Database\Factories\AnaliseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Analise extends Modelo
{
    /** @use HasFactory<AnaliseFactory> */
    use HasFactory;

    protected $table = 'analises';

    protected $fillable = [
        'projeto_id', 'status', 'iniciado_em', 'finalizado_em', 'duracao_segundos',
        'pontuacao', 'nivel_risco', 'resumo', 'mensagem_erro', 'configuracao_analise',
        'versoes_analisadores',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusAnalise::class,
            'nivel_risco' => NivelRisco::class,
            'iniciado_em' => 'datetime',
            'finalizado_em' => 'datetime',
            'configuracao_analise' => 'array',
            'versoes_analisadores' => 'array',
        ];
    }

    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }

    public function achados(): HasMany
    {
        return $this->hasMany(Achado::class, 'analise_id');
    }

    public function dependencias(): HasMany
    {
        return $this->hasMany(Dependencia::class, 'analise_id');
    }

    public function metricasArquivos(): HasMany
    {
        return $this->hasMany(MetricaArquivo::class, 'analise_id');
    }

    public function registrosRotas(): HasMany
    {
        return $this->hasMany(RegistroRota::class, 'analise_id');
    }

    public function lacunasTestes(): HasMany
    {
        return $this->hasMany(LacunaTeste::class, 'analise_id');
    }

    public function etapasModernizacao(): HasMany
    {
        return $this->hasMany(EtapaModernizacao::class, 'analise_id');
    }

    public function relatorios(): HasMany
    {
        return $this->hasMany(Relatorio::class, 'analise_id');
    }

    public function rascunhosIssuesGithub(): HasMany
    {
        return $this->hasMany(RascunhoIssueGithub::class, 'analise_id');
    }
}
