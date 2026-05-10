<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Tabla intermedia que se usa para agrupar todas o parcialmente las recepciones de una OC
 * pertenecientes a un comprobante (factura/boleta) especifico.
 */
class OrdenCompraComprobanteRecepcion extends Model
{
    protected $table = 'orden_compra_comprobante_recepcion';

    public $timestamps = false;

    protected $fillable = [
        'id_orden_compra_recepcion',
        'id_orden_compra_comprobante',
    ];

    public static function get_recepciones_agrupadas(int $id_comprobante)
    {
        $sql = '
        SELECT
            cr.id_orden_compra_comprobante,
            cr.id_orden_compra_recepcion,
            --
            r.id_orden_compra,
            r.numero_correlativo,
            r.id_almacen_recepcionista,
            alm.nombre as almacen_recepcionista,
            alm.es_principal as para_un_almacen_principal,
            CONCAT(e.nombre, " ", e.apellido) AS empleado_recepcion,
            r.fecha_hora_recepcion,
            CONCAT(r.serie_guia_remision, "-", r.numero_guia_remision) as guia_remision,
            r.estado
        FROM
            orden_compra_comprobante_recepcion cr
        INNER JOIN orden_compra_recepcion r ON r.id = cr.id_orden_compra_recepcion
        INNER JOIN almacen alm ON alm.id = r.id_almacen_recepcionista
        INNER JOIN empleado e ON e.id = r.id_empleado_recepcion
        WHERE 
            cr.id_orden_compra_comprobante = :id_comprobante
        ORDER BY r.numero_correlativo ASC
        ';

        return DB::select($sql, ['id_comprobante' => $id_comprobante]);
    }
}
