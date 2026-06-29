<?php

namespace Database\Factories;

use App\Models\Analise;
use App\Models\LacunaTeste;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LacunaTeste>
 */
class LacunaTesteFactory extends Factory
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
            'tipo_alvo' => 'controlador',
            'nome_alvo' => 'LegacyController',
            'caminho_alvo' => 'app/Http/Controllers/LegacyController.php',
            'caminho_teste_esperado' => 'tests/Feature/LegacyControllerTest.php',
            'confianca' => 75,
        ];
    }
}
