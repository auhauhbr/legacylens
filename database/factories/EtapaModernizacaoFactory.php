<?php

namespace Database\Factories;

use App\Models\Analise;
use App\Models\EtapaModernizacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EtapaModernizacao>
 */
class EtapaModernizacaoFactory extends Factory
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
            'fase' => 1,
            'posicao' => fake()->unique()->numberBetween(1, 1000),
            'prioridade' => 'alta',
            'titulo' => 'Criar rede de segurança',
            'descricao' => fake()->sentence(),
            'esforco' => 'medio',
            'risco' => 'alto',
            'criterios_aceite' => ['Testes críticos executam no CI'],
            'ids_achados_relacionados' => [],
        ];
    }
}
