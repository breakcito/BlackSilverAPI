<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrestamoAlmacenRecepcion extends Model
{
    protected $table = 'prestamo_almacen_recepcion';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_reposicion',
        'id_empleado_registro', // quien recibe
        'observacion',
        'fecha_hora_recepcion',
        'evidencias', // [{"url": "", "path_relativo": "", "nombre_original": "", "extension": ""}, ...]
        'con_incidencia', // 1 | 0
        'created_at', // automatico
        'estado', // Recepcionado Parcialmente | Recepcionado
    ];
}
