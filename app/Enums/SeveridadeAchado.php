<?php

namespace App\Enums;

enum SeveridadeAchado: string
{
    case Critica = 'critica';
    case Alta = 'alta';
    case Media = 'media';
    case Baixa = 'baixa';
    case Informativa = 'informativa';
}
