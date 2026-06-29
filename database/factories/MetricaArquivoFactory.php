<?php

namespace Database\Factories;

use App\Models\Analise;
use App\Models\MetricaArquivo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetricaArquivo>
 */
class MetricaArquivoFactory extends Factory
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
            'caminho_arquivo' => 'app/Http/Controllers/'.fake()->word().'Controller.php',
            'tipo_arquivo' => 'controlador',
            'total_linhas' => fake()->numberBetween(100, 800),
            'total_metodos' => fake()->numberBetween(3, 25),
            'total_classes' => 1,
            'complexidade_estimada' => fake()->numberBetween(5, 50),
            'controlador' => true,
            'modelo' => false,
            'servico' => false,
        ];
    }
}
