<?php

namespace App\Enums;

enum StatusAnalise: string
{
    case Pendente = 'pendente';
    case EmExecucao = 'em_execucao';
    case Concluida = 'concluida';
    case Falhou = 'falhou';
    case Cancelada = 'cancelada';
}
