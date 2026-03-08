<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submodulo extends Model
{
    protected $table = 'submodulo';
    public $timestamps = false;
    protected $fillable = [
        'id_modulo',
        'nombre',
        'path',
        'estado',
    ];
}
