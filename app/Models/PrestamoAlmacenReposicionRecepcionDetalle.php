<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrestamoAlmacenReposicionRecepcionDetalle extends Model
{
    protected $table = 'prestamo_almacen_reposicion_recepcion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_reposicion_detalle',
        'cantidad_recepcionada_base',
        'estado', // Recepcionado parcialmente | Recepcionado
    ];
}
