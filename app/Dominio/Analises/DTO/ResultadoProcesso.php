<?php

namespace App\Dominio\Analises\DTO;

final readonly class ResultadoProcesso
{
    public function __construct(
        public string $chaveComando,
        public ?int $codigoSaida,
        public bool $sucesso,
        public string $saidaPadrao,
        public string $saidaErro,
        public bool $tempoExcedido,
        public int $duracaoMilissegundos,
    ) {}
}
