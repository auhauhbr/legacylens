<?php

namespace App\Enums;

enum StatusRascunhoIssue: string
{
    case Rascunho = 'rascunho';
    case Aprovado = 'aprovado';
    case Publicado = 'publicado';
    case Rejeitado = 'rejeitado';
}
