<?php

namespace Database\Factories;

use App\Models\Analise;
use App\Models\RegistroRota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistroRota>
 */
class RegistroRotaFactory extends Factory
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
            'metodo' => 'GET',
            'uri' => '/api/'.fake()->slug(),
            'nome' => fake()->slug('.'),
            'controlador' => 'App\\Http\\Controllers\\LegacyController',
            'acao' => 'App\\Http\\Controllers\\LegacyController@index',
            'middlewares' => ['api', 'auth:sanctum'],
            'arquivo_origem' => 'routes/api.php',
            'metadados' => [],
        ];
    }
}
