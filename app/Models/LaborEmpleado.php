<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaborEmpleado extends Model
{
    protected $table = 'labor_empleado';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado',
        'id_labor',
    ];
}
