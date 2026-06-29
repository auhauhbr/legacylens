<?php

namespace Database\Factories;

use App\Enums\TipoOrigemProjeto;
use App\Enums\TipoProjeto;
use App\Models\Projeto;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Projeto>
 */
class ProjetoFactory extends Factory
{
    /**
     * Define o estado padrão do modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'nome' => fake()->words(3, true),
            'descricao' => fake()->sentence(),
            'tipo' => TipoProjeto::Laravel,
            'tipo_origem' => TipoOrigemProjeto::Local,
            'caminho_local' => '/srv/projetos/'.fake()->slug(),
            'url_repositorio' => 'https://github.com/exemplo/'.fake()->slug().'.git',
            'ramo' => 'main',
            'configuracao_padrao_analise' => ['analisadores' => ['composer', 'testes']],
        ];
    }
}
