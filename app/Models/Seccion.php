<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seccion extends Model
{
    protected $table = 'seccion';
    public $timestamps = false;
    protected $fillable = [
        'id_submodulo',
        'nombre',
        'path',
        'estado',
    ];
}
