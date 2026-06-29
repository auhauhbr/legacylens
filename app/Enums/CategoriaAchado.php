<?php

namespace App\Enums;

enum CategoriaAchado: string
{
    case Dependencias = 'dependencias';
    case Seguranca = 'seguranca';
    case Testes = 'testes';
    case Arquitetura = 'arquitetura';
    case Rotas = 'rotas';
    case Documentacao = 'documentacao';
    case IntegracaoContinua = 'integracao_continua';
    case Estilo = 'estilo';
    case Desempenho = 'desempenho';
}
