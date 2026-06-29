<?php

namespace Database\Factories;

use App\Enums\NivelRisco;
use App\Enums\StatusAnalise;
use App\Models\Analise;
use App\Models\Projeto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Analise>
 */
class AnaliseFactory extends Factory
{
    /**
     * Define o estado padrão do modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = fake()->dateTimeBetween('-30 days', '-1 hour');
        $fim = (clone $inicio)->modify('+2 minutes');

        return [
            'projeto_id' => Projeto::factory(),
            'status' => StatusAnalise::Concluida,
            'iniciado_em' => $inicio,
            'finalizado_em' => $fim,
            'duracao_segundos' => 120,
            'pontuacao' => 68,
            'nivel_risco' => NivelRisco::Moderado,
            'resumo' => fake()->sentence(),
            'mensagem_erro' => null,
            'configuracao_analise' => ['tempo_limite_segundos' => 30],
            'versoes_analisadores' => ['composer' => '1.0.0'],
        ];
    }
}
