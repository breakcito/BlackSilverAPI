<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacenDetalle extends Model
{
    protected $table = 'requerimiento_almacen_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen',
        'id_producto',
        'id_unidad_medida_presentacion', // caja
        'id_empleado_atencion', // quien decide aprobar/rechazar el producto del requerimiento
        //
        'cantidad_solicitada', // 3 cajas
        'cantidad_solicitada_base', // 30kg
        'cantidad_entregada', // 2 cajas
        'cantidad_entregada_base', // 20kg
        'comentario',
        'comentario_decision', // luego de aprobar/rechazar, podran brindar algun comentario adicional
        //
        'estado',
    ];

    public static function get_detalles_by_requerimiento(int $id_requerimiento)
    {
        $sql = "
        SELECT
            rad.id AS id_requerimiento_detalle,
            rad.id_producto,
            p.nombre AS producto,
            p.es_fiscalizado,
            p.es_perecible,
            rad.id_unidad_medida,
            um.abreviatura AS unidad_medida,
            rad.cantidad_solicitada,
            rad.cantidad_atendida,
            rad.comentario,
            rad.comentario_rechazo,
            rad.estado,
            (SELECT IFNULL(SUM(lp.stock_actual), 0) 
             FROM lote_producto lp 
             WHERE lp.id_producto = rad.id_producto 
             AND lp.id_almacen = ra.id_almacen_destino 
             AND lp.estado = 'Activo'
            ) as stock_disponible
        FROM
            requerimiento_almacen_detalle rad
        INNER JOIN requerimiento_almacen ra ON ra.id = rad.id_requerimiento
        INNER JOIN producto p ON p.id = rad.id_producto
        INNER JOIN unidad_medida um ON um.id = rad.id_unidad_medida
        WHERE
            rad.id_requerimiento = :id_requerimiento
        ";

        $detalles = DB::select($sql, ['id_requerimiento' => $id_requerimiento]);

        return array_map(function ($detalle) {
            $detalle->es_fiscalizado = (bool) $detalle->es_fiscalizado;
            $detalle->es_perecible = (bool) $detalle->es_perecible;

            return $detalle;
        }, $detalles);
    }
}
