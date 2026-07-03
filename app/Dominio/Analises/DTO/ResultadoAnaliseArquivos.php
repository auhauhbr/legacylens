<?php

namespace App\Dominio\Analises\DTO;

class ResultadoAnaliseArquivos
{
    /**
     * @param  list<DadosAchado>  $achados
     * @param  list<array{caminho_arquivo: string, tipo_arquivo: string, total_linhas: int, complexidade_estimada: int}>  $metricas
     */
    public function __construct(
        public readonly array $achados,
        public readonly array $metricas,
    ) {}
}
