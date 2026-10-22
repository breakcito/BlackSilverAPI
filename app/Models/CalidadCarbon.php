<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalidadCarbon extends Model
{
    protected $table = 'calidad_carbon';

    public $timestamps = false;

    protected $fillable = [
        'nombre', // enum TipoCalidadCarbon: Baja | Intermedia | Alta
        'porcentaje_inicio_ceniza',
        'porcentaje_fin_ceniza',
    ];
}
