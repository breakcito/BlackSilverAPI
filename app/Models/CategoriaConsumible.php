<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaConsumible extends Model
{
    protected $table = 'categoria_consumible';

    public $timestamps = false;

    protected $fillable = [
        'id_categoria_consumible',
        'id_categoria_consumidora',
    ];
}
