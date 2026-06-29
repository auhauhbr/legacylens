<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class Modelo extends Model
{
    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = 'atualizado_em';
}
