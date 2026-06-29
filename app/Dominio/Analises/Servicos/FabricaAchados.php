<?php

namespace App\Dominio\Analises\Servicos;

use App\Dominio\Analises\DTO\DadosAchado;
use App\Models\Achado;
use App\Models\Analise;

class FabricaAchados
{
    public function criar(Analise $analise, DadosAchado $dados): Achado
    {
        $impressaoDigital = hash('sha256', implode('|', [
            $dados->codigo,
            $dados->caminhoArquivo ?? '',
            (string) ($dados->linhaInicial ?? ''),
        ]));

        return Achado::query()->updateOrCreate([
            'analise_id' => $analise->id,
            'impressao_digital' => $impressaoDigital,
        ], [
            'codigo' => $dados->codigo,
            'categoria' => $dados->categoria,
            'severidade' => $dados->severidade,
            'confianca' => $dados->confianca,
            'titulo' => $dados->titulo,
            'descricao' => $dados->descricao,
            'recomendacao' => $dados->recomendacao,
            'caminho_arquivo' => $dados->caminhoArquivo,
            'linha_inicial' => $dados->linhaInicial,
            'linha_final' => $dados->linhaFinal,
            'evidencia' => $dados->evidencia,
            'metadados' => $dados->metadados,
        ]);
    }
}
