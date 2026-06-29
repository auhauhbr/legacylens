<?php

namespace App\Models;

use App\Enums\StatusRascunhoIssue;
use Database\Factories\RascunhoIssueGithubFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RascunhoIssueGithub extends Modelo
{
    /** @use HasFactory<RascunhoIssueGithubFactory> */
    use HasFactory;

    protected $table = 'rascunhos_issues_github';

    protected $fillable = [
        'analise_id', 'achado_id', 'etapa_modernizacao_id', 'titulo', 'corpo', 'rotulos',
        'status', 'url_issue_github', 'publicado_em', 'metadados',
    ];

    protected function casts(): array
    {
        return [
            'rotulos' => 'array',
            'status' => StatusRascunhoIssue::class,
            'publicado_em' => 'datetime',
            'metadados' => 'array',
        ];
    }

    public function analise(): BelongsTo
    {
        return $this->belongsTo(Analise::class, 'analise_id');
    }

    public function achado(): BelongsTo
    {
        return $this->belongsTo(Achado::class, 'achado_id');
    }

    public function etapaModernizacao(): BelongsTo
    {
        return $this->belongsTo(EtapaModernizacao::class, 'etapa_modernizacao_id');
    }
}
