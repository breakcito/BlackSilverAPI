<?php

namespace App\Views\PrestamosAlmacen\Data;

use Illuminate\Support\Facades\DB;

class EntregasDetalleData
{

    /**
     * Obtiene los detalles de una entrega específica (lotes, cantidades, producto).
     */
    public static function get_detalles_entrega(int $id_entrega): array
    {
        return DB::select('
            SELECT
                paed.id AS id_entrega_detalle,
                paed.id_prestamo_almacen_detalle,
                prod.id AS id_producto,
                prod.nombre AS producto,
                lote.id AS id_lote_salida,
                lote.correlativo AS correlativo_lote,
                lote.fecha_vencimiento,
                DATEDIFF(lote.fecha_vencimiento, NOW()) AS dias_para_vencer,
                um.id as id_unidad_medida_sol, -- unidad de medida de la solicitud/prestamo
                um.nombre AS unidad_medida_sol,
                um.abreviatura AS unidad_medida_sol_abv,
                umb.id as id_unidad_medida_base, -- unidad de medida base del producto
                umb.nombre as unidad_medida_base,
                umb.abreviatura as unidad_medida_abv,
                paed.cantidad, -- cantidad entregada en base a la unidad de medida de la solicitud
                srd.contenido_por_presentacion, -- cuantas unidades base hay por una unidad de la solicitud
                paed.cantidad_base, -- cantidad entregada en base a la unidad de medida base del producto
                pad.comentario,
                paed.estado
            FROM
                prestamo_almacen_entrega_detalle paed
            INNER JOIN prestamo_almacen_detalle pad ON pad.id = paed.id_prestamo_almacen_detalle
            INNER JOIN lote_producto lote ON lote.id = paed.id_lote_salida
            INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = pad.id_solicitud_reabastecimiento_detalle
            INNER JOIN producto prod ON prod.id = srd.id_producto
            INNER JOIN unidad_medida um ON um.id = srd.id_unidad_medida
            INNER JOIN unidad_medida umb ON umb.id = prod.id_unidad_medida_base
            WHERE paed.id_prestamo_almacen_entrega = :id_entrega
            ORDER BY prod.nombre ASC
        ', ['id_entrega' => $id_entrega]);
    }
}
