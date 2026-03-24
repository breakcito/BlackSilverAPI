<?php

namespace App\Views\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacenEntregaDetalle;
use App\Shared\Enums\PrestamoAlmacen\EstadoEntregaPrestamo;
use Illuminate\Support\Facades\DB;

class EntregasDetalleData
{
    /**
     * Crea un detalle de entrega (un lote de salida para un ítem del préstamo).
     */
    public static function crear_detalle_entrega(
        int $id_entrega,
        int $id_prestamo_detalle,
        int $id_lote_salida,
        float $cantidad
    ): int {
        return PrestamoAlmacenEntregaDetalle::insertGetId([
            'id_prestamo_almacen_entrega' => $id_entrega,
            'id_prestamo_almacen_detalle' => $id_prestamo_detalle,
            'id_lote_salida'              => $id_lote_salida,
            'id_lote_ingreso'             => null, 
            'cantidad'                    => $cantidad,
            'estado'                      => EstadoEntregaPrestamo::EnDespacho->value,
        ]);
    }

    /**
     * Obtiene los detalles de una entrega específica (lotes, cantidades, producto).
     */
    public static function get_detalles_entrega(int $id_entrega): array
    {
        return DB::select('
            SELECT
                paed.id AS id_entrega_detalle,
                paed.id_prestamo_almacen_detalle,
                paed.cantidad,
                paed.estado,
                pad.comentario,
                lote.id AS id_lote_salida,
                lote.correlativo AS correlativo_lote,
                lote.fecha_vencimiento,
                DATEDIFF(lote.fecha_vencimiento, NOW()) AS dias_para_vencer,
                prod.id AS id_producto,
                prod.nombre AS producto,
                um.nombre AS unidad_medida,
                um.abreviatura AS unidad_medida_abv,
                srd.contenido_por_presentacion
            FROM
                prestamo_almacen_entrega_detalle paed
            INNER JOIN prestamo_almacen_detalle pad ON pad.id = paed.id_prestamo_almacen_detalle
            INNER JOIN lote_producto lote ON lote.id = paed.id_lote_salida
            INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = pad.id_solicitud_reabastecimiento_detalle
            INNER JOIN producto prod ON prod.id = srd.id_producto
            INNER JOIN unidad_medida um ON um.id = srd.id_unidad_medida
            WHERE paed.id_prestamo_almacen_entrega = :id_entrega
            ORDER BY prod.nombre ASC
        ', ['id_entrega' => $id_entrega]);
    }
}
