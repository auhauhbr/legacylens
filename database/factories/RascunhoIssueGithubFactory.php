<?php

namespace Database\Factories;

use App\Enums\StatusRascunhoIssue;
use App\Models\Analise;
use App\Models\RascunhoIssueGithub;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RascunhoIssueGithub>
 */
class RascunhoIssueGithubFactory extends Factory
{
    /**
     * Define o estado padrão do modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'analise_id' => Analise::factory(),
            'achado_id' => null,
            'etapa_modernizacao_id' => null,
            'titulo' => 'Adicionar testes de caracterização',
            'corpo' => "## Problema\n\nO fluxo crítico não possui testes automatizados.",
            'rotulos' => ['testes', 'alta'],
            'status' => StatusRascunhoIssue::Rascunho,
            'url_issue_github' => null,
            'publicado_em' => null,
            'metadados' => [],
        ];
    }
}
