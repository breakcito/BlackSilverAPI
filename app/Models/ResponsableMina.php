<?php

namespace App\Models;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ResponsableMina extends Model
{
    protected $table = 'responsable_mina';

    public $timestamps = false;

    protected $fillable = [
        'id_mina',
        'id_empleado',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];
}
