<?php

namespace App\Dominio\Analises\DTO;

final readonly class ResultadoAnaliseComposer
{
    /**
     * @param  list<DadosAchado>  $achados
     * @param  list<array{nome_pacote: string, restricao: string, versao_atual: ?string, escopo: string, desenvolvimento: bool}>  $dependencias
     */
    public function __construct(
        public array $achados,
        public array $dependencias,
    ) {}
}
