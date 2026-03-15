<?php

namespace App\Models;

use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Database\Eloquent\Model;

class SolicitudReabastecimiento extends Model
{
    protected $table = 'solicitud_reabastecimiento';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen_solicitante',
        'id_requerimiento_almacen', // null - sirve para saber si fue generado por un requerimiento
        'id_empleado_solicitante',
        'correlativo',
        'numero_correlativo',
        'observacion',
        'premura',
        'fecha_entrega_requerida',
        'created_at',
        'estado',
    ];

    // Helper que ayuda a calcular el siguiente correlativo - reseteo anual
    public static function get_nuevo_correlativo(int $id_almacen_solicitante)
    {
        return CorrelativoHelper::generar(
            'solicitud_reabastecimiento',
            'SCR',
            ["id_almacen_solicitante" => $id_almacen_solicitante]
        );
    }
}
