<?php

namespace App\Modules\Empresas\Models;

use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = "area";
    public $timestamps = false;
    protected $fillable = [
        'nombre',
        'estado',
    ];
}
