<?php

namespace App\Dominio\Analises\DTO;

use App\Enums\CategoriaAchado;
use App\Enums\NivelConfianca;
use App\Enums\SeveridadeAchado;

final readonly class DadosAchado
{
    public function __construct(
        public string $codigo,
        public CategoriaAchado $categoria,
        public SeveridadeAchado $severidade,
        public string $titulo,
        public string $descricao,
        public ?string $recomendacao = null,
        public ?string $caminhoArquivo = null,
        public ?int $linhaInicial = null,
        public ?int $linhaFinal = null,
        public array $evidencia = [],
        public array $metadados = [],
        public NivelConfianca $confianca = NivelConfianca::Alta,
    ) {}
}
