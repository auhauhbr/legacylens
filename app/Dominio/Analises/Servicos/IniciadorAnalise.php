<?php

namespace App\Dominio\Analises\Servicos;

use App\Enums\StatusAnalise;
use App\Jobs\ExecutarAnaliseProjeto;
use App\Models\Analise;
use App\Models\Projeto;

class IniciadorAnalise
{
    public function iniciar(Projeto $projeto): Analise
    {
        $analise = $projeto->analises()->create([
            'status' => StatusAnalise::Pendente,
            'configuracao_analise' => $projeto->configuracao_padrao_analise ?? [],
        ]);

        ExecutarAnaliseProjeto::dispatch($analise->id);

        return $analise;
    }
}
