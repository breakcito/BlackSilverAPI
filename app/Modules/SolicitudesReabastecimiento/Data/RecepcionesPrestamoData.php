<?php

namespace App\Modules\SolicitudesReabastecimiento\Data;

use App\Models\PrestamoAlmacenEntrega;
use App\Models\PrestamoAlmacenEntregaDetalle;
use App\Models\PrestamoAlmacenEntregaRecepcion;
use App\Models\PrestamoAlmacenEntregaRecepcionDetalle;

class RecepcionesPrestamoData
{
    /**
     * Crear una cabecera de recepción de préstamo
     */
    public static function crear_recepcion(
        int $id_entrega,
        int $id_empleado,
        string $fecha_hora_recepcion,
        ?string $observacion,
        ?string $evidencias,
        bool $con_incidencia
    ) {
        return PrestamoAlmacenEntregaRecepcion::insertGetId([
            'id_prestamo_almacen_entrega' => $id_entrega,
            'id_empleado_registro' => $id_empleado,
            'observacion' => $observacion,
            'fecha_hora_recepcion' => $fecha_hora_recepcion,
            'evidencias' => $evidencias,
            'con_incidencia' => $con_incidencia ? 1 : 0,
            'created_at' => now(),
            'estado' => 'Recepcionado',
        ]);
    }

    /**
     * Crear un detalle de recepción de préstamo
     */
    public static function crear_detalle_recepcion(
        int $id_recepcion,
        int $id_entrega_detalle,
        float $cantidad_recepcionada_base
    ) {
        return PrestamoAlmacenEntregaRecepcionDetalle::insertGetId([
            'id_prestamo_almacen_recepcion' => $id_recepcion,
            'id_prestamo_almacen_entrega_detalle' => $id_entrega_detalle,
            'cantidad_recepcionada_base' => $cantidad_recepcionada_base,
            'estado' => 'Recepcionado',
        ]);
    }

    /**
     * Obtener el historial de recepciones de una entrega de préstamo
     */
    public static function get_historial_recepciones(int $id_entrega)
    {
        return PrestamoAlmacenEntregaRecepcion::get_recepciones(id_entrega: $id_entrega);
    }

    /**
     * Obtener detalles de una recepción de préstamo
     */
    public static function get_detalles_recepcion(int $id_recepcion)
    {
        return PrestamoAlmacenEntregaRecepcionDetalle::get_detalles(id_recepcion: $id_recepcion);
    }

    /**
     * Obtener la cantidad total recepcionada base para un detalle de entrega de préstamo
     */
    public static function get_cantidad_recepcionada_total_base_detalle(int $id_entrega_detalle): float
    {
        return (float) PrestamoAlmacenEntregaRecepcionDetalle::where('id_prestamo_almacen_entrega_detalle', $id_entrega_detalle)
            ->sum('cantidad_recepcionada_base');
    }

    /**
     * Obtener un detalle de entrega de préstamo por ID
     */
    public static function get_entrega_detalle_by_id(int $id_entrega_detalle)
    {
        return PrestamoAlmacenEntregaDetalle::where('id', $id_entrega_detalle)
            ->first();
    }

    /**
     * Actualiza el estado de un detalle de entrega de préstamo
     */
    public static function update_entrega_detalle_estado(int $id_entrega_detalle, string $estado)
    {
        return PrestamoAlmacenEntregaDetalle::where('id', $id_entrega_detalle)
            ->update(['estado' => $estado]);
    }

    /**
     * Obtiene todos los detalles de una entrega de préstamo
     */
    public static function get_entrega_detalles(int $id_entrega)
    {
        return PrestamoAlmacenEntregaDetalle::where('id_prestamo_almacen_entrega', $id_entrega)
            ->get();
    }

    /**
     * Actualiza el estado de la cabecera de entrega de préstamo
     */
    public static function update_entrega_estado(int $id_entrega, string $estado)
    {
        return PrestamoAlmacenEntrega::where('id', $id_entrega)
            ->update(['estado' => $estado]);
    }

    /**
     * Obtener el correlativo de una entrega de préstamo
     */
    public static function get_correlativo_entrega(int $id_entrega)
    {
        return PrestamoAlmacenEntrega::where('id', $id_entrega)
            ->value('correlativo');
    }
}
