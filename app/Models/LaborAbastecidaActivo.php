<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// esta tabla asociara todas las labores a las que un activo/maquinaria abastece
class LaborAbastecidaActivo extends Model
{
    protected $table = 'labor_abastecida_activo';

    public $timestamps = false;

    protected $fillable = [
        'id_activo_fijo',
        'id_labor',
    ];
}
