<?php

namespace App\Enums;

enum NivelRisco: string
{
    case Saudavel = 'saudavel';
    case AtencaoLeve = 'atencao_leve';
    case Moderado = 'moderado';
    case Alta = 'alto';
    case Critica = 'critico';
}
