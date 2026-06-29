<?php

namespace Database\Factories;

use App\Models\Analise;
use App\Models\Dependencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dependencia>
 */
class DependenciaFactory extends Factory
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
            'nome_pacote' => fake()->randomElement(['laravel/framework', 'guzzlehttp/guzzle', 'monolog/monolog']),
            'versao_atual' => '8.0.0',
            'versao_mais_recente' => '12.0.0',
            'restricao' => '^8.0',
            'escopo' => 'producao',
            'direta' => true,
            'desenvolvimento' => false,
            'abandonada' => false,
            'possui_alerta_seguranca' => false,
            'metadados' => [],
        ];
    }
}
