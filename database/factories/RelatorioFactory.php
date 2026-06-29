<?php

namespace Database\Factories;

use App\Enums\TipoRelatorio;
use App\Models\Analise;
use App\Models\Relatorio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Relatorio>
 */
class RelatorioFactory extends Factory
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
            'tipo' => TipoRelatorio::Executivo,
            'titulo' => 'Relatório executivo',
            'conteudo_markdown' => "# Relatório executivo\n\nO projeto exige atenção técnica.",
        ];
    }
}
