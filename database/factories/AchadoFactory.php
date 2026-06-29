<?php

namespace Database\Factories;

use App\Enums\CategoriaAchado;
use App\Enums\NivelConfianca;
use App\Enums\SeveridadeAchado;
use App\Models\Achado;
use App\Models\Analise;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Achado>
 */
class AchadoFactory extends Factory
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
            'codigo' => 'testes.ausentes',
            'categoria' => CategoriaAchado::Testes,
            'severidade' => SeveridadeAchado::Alta,
            'confianca' => NivelConfianca::Alta,
            'titulo' => 'Cobertura de testes ausente',
            'descricao' => fake()->sentence(),
            'recomendacao' => fake()->sentence(),
            'caminho_arquivo' => 'tests',
            'linha_inicial' => null,
            'linha_final' => null,
            'evidencia' => ['diretorio_testes' => false],
            'metadados' => ['origem' => 'massa_teste'],
            'impressao_digital' => hash('sha256', Str::uuid()->toString()),
        ];
    }
}
