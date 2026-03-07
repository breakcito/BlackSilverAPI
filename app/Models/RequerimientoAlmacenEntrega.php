<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacenEntrega extends Model
{
    protected $table = 'requerimiento_almacen_entrega';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen',
        'id_empleado_entrega',
        'id_empleado_recibe',
        'correlativo',
        'numero_correlativo',
        'fecha_hora_entrega',
        'observacion',
        'evidencias',
        'created_at',
        'estado',
    ];

    public function detalles()
    {
        return $this->hasMany(RequerimientoAlmacenEntregaDetalle::class, 'id_requerimiento_almacen_entrega');
    }

    /**
     * Obtener el historial de entregas de un ítem de requerimiento.
     */
    public static function get_historial_por_detalle_item(int $id_detalle)
    {
        $sql = "
            SELECT 
                rae.id AS id_entrega,
                rae.correlativo AS codigo_entrega,
                rae.fecha_hora_entrega AS fecha_entrega,
                CONCAT(er.nombre, ' ', er.apellido) AS entregado_a,
                raed.cantidad_base AS cantidad,
                CONCAT(ee.nombre, ' ', ee.apellido) AS usuario_entrega
            FROM requerimiento_almacen_entrega_detalle raed
            INNER JOIN requerimiento_almacen_entrega rae ON rae.id = raed.id_requerimiento_almacen_entrega
            INNER JOIN empleado ee ON ee.id = rae.id_empleado_entrega
            INNER JOIN empleado er ON er.id = rae.id_empleado_recibe
            WHERE raed.id_requerimiento_almacen_detalle = :id_detalle
            ORDER BY rae.fecha_hora_entrega DESC
        ";

        return DB::select($sql, ['id_detalle' => $id_detalle]);
    }
}
