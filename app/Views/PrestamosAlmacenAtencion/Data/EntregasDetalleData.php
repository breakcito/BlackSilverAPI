<?php

namespace App\Views\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacenEntrega;
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
        float $cantidad,
        float $cantidad_base,
        ?string $comentario = null
    ): int {
        return PrestamoAlmacenEntregaDetalle::insertGetId([
            'id_prestamo_almacen_entrega' => $id_entrega,
            'id_prestamo_almacen_detalle' => $id_prestamo_detalle,
            'id_lote_salida'              => $id_lote_salida,
            'cantidad'                    => $cantidad,
            'cantidad_base'               => $cantidad_base,
            'comentario'                  => $comentario,
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
                pad.id_solicitud_reabastecimiento_detalle,
                paed.id_prestamo_almacen_entrega AS id_reabastecimiento_entrega,
                paed.cantidad AS cantidad_lote,
                (paed.cantidad * lote.contenido_por_presentacion) AS cantidad_base,
                paed.estado AS estado,
                COALESCE((
                    SELECT SUM(rd.cantidad_recepcionada_base)
                    FROM prestamo_almacen_recepcion_detalle rd
                    WHERE rd.id_prestamo_almacen_entrega_detalle = paed.id
                ), 0) AS cantidad_recibida_total,
                pad.comentario,
                lote.id AS id_lote_salida,
                lote.correlativo AS correlativo_lote,
                lote.fecha_vencimiento,
                DATEDIFF(lote.fecha_vencimiento, NOW()) AS dias_para_vencer,
                lote.id_unidad_medida AS id_unidad_medida,
                prod.id AS id_producto,
                prod.nombre AS producto,
                prod.es_perecible,
                prod.id_unidad_medida_base,
                um.abreviatura AS unidad_lote_abv,
                um_base.abreviatura AS unidad_base_abv,
                um.nombre AS unidad_medida,
                um.abreviatura AS unidad_medida_abv,
                srd.contenido_por_presentacion,
                srd.id_unidad_medida AS id_unidad_medida_solicitada,
                srd.contenido_por_presentacion AS contenido_por_presentacion_solicitado,
                um_sol.abreviatura AS unidad_medida_solicitud_abv
            FROM
                prestamo_almacen_entrega_detalle paed
            INNER JOIN prestamo_almacen_detalle pad ON pad.id = paed.id_prestamo_almacen_detalle
            INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = pad.id_solicitud_reabastecimiento_detalle
            INNER JOIN lote_producto lote ON lote.id = paed.id_lote_salida
            INNER JOIN producto prod ON prod.id = srd.id_producto
            INNER JOIN unidad_medida um ON um.id = lote.id_unidad_medida
            INNER JOIN unidad_medida um_base ON um_base.id = prod.id_unidad_medida_base
            INNER JOIN unidad_medida um_sol ON um_sol.id = srd.id_unidad_medida
            WHERE paed.id_prestamo_almacen_entrega = :id_entrega
            ORDER BY prod.nombre ASC
        ', ['id_entrega' => $id_entrega]);
    }

    /**
     * Marca un detalle como recibido y vincula el lote de ingreso.
     */
    public static function marcar_como_recibido(int $id_entrega_detalle): bool
    {
        return (bool) PrestamoAlmacenEntregaDetalle::where('id', $id_entrega_detalle)
            ->update([
                'estado'          => EstadoEntregaPrestamo::Confirmada->value
            ]);
    }

    /**
     * Verifica si todos los detalles de la entrega están recibidos/anulados para cerrar la entrega (Status cabecera).
     */
    public static function verificar_y_completar_entrega(int $id_entrega): void
    {
        $pendientes = PrestamoAlmacenEntregaDetalle::where('id_prestamo_almacen_entrega', $id_entrega)
            ->where('estado', '!=', EstadoEntregaPrestamo::Confirmada->value)
            ->where('estado', '!=', EstadoEntregaPrestamo::Anulada->value)
            ->count();

        if ($pendientes === 0) {
            PrestamoAlmacenEntrega::where('id', $id_entrega)
                ->update(['estado' => EstadoEntregaPrestamo::Confirmada->value]);
        }
    }
}
