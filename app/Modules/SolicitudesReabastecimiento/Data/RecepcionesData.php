<?php

namespace App\Modules\SolicitudesReabastecimiento\Data;

use App\Models\SolicitudReabastecimientoRecepcion;
use App\Models\SolicitudReabastecimientoRecepcionDetalle;
use App\Models\SolicitudReabastecimientoEntrega;
use App\Models\SolicitudReabastecimientoEntregaDetalle;

class RecepcionesData
{
    /**
     * Crear una cabecera de recepción logística
     */
    public static function crear_recepcion(
        int $id_entrega,
        int $id_empleado,
        string $fecha_hora_recepcion,
        ?string $observacion,
        ?string $evidencias,
        bool $con_incidencia
    ) {
        return SolicitudReabastecimientoRecepcion::insertGetId([
            'id_solicitud_reabastecimiento_entrega' => $id_entrega,
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
     * Crear un detalle de recepción logística
     */
    public static function crear_detalle_recepcion(
        int $id_recepcion,
        int $id_entrega_detalle,
        float $cantidad_recepcionada_base
    ) {
        return SolicitudReabastecimientoRecepcionDetalle::insertGetId([
            'id_solicitud_reabastecimiento_recepcion' => $id_recepcion,
            'id_solicitud_reabastecimiento_entrega_detalle' => $id_entrega_detalle,
            'cantidad_recepcionada_base' => $cantidad_recepcionada_base,
            'estado' => 'Recepcionado',
        ]);
    }

    /**
     * Obtener el historial de recepciones de una entrega logística
     */
    public static function get_historial_recepciones(int $id_entrega)
    {
        return SolicitudReabastecimientoRecepcion::get_recepciones(id_entrega: $id_entrega);
    }

    /**
     * Obtener detalles de una recepción logística
     */
    public static function get_detalles_recepcion(int $id_recepcion)
    {
        return SolicitudReabastecimientoRecepcionDetalle::get_detalles(id_recepcion: $id_recepcion);
    }

    /**
     * Obtener la cantidad total recepcionada base para un detalle de entrega
     */
    public static function get_cantidad_recepcionada_total_base_detalle(int $id_entrega_detalle): float
    {
        return (float) SolicitudReabastecimientoRecepcionDetalle::where('id_solicitud_reabastecimiento_entrega_detalle', $id_entrega_detalle)
            ->sum('cantidad_recepcionada_base');
    }

    /**
     * Obtener un detalle de entrega por ID
     */
    public static function get_entrega_detalle_by_id(int $id_entrega_detalle)
    {
        return SolicitudReabastecimientoEntregaDetalle::where('id', $id_entrega_detalle)
            ->first();
    }

    /**
     * Actualiza el estado de un detalle de entrega
     */
    public static function update_entrega_detalle_estado(int $id_entrega_detalle, string $estado)
    {
        return SolicitudReabastecimientoEntregaDetalle::where('id', $id_entrega_detalle)
            ->update(['estado' => $estado]);
    }

    /**
     * Obtiene todos los detalles de una entrega
     */
    public static function get_entrega_detalles(int $id_entrega)
    {
        return SolicitudReabastecimientoEntregaDetalle::where('id_reabastecimiento_entrega', $id_entrega)
            ->get();
    }

    /**
     * Actualiza el estado de la cabecera de entrega
     */
    public static function update_entrega_estado(int $id_entrega, string $estado)
    {
        return SolicitudReabastecimientoEntrega::where('id', $id_entrega)
            ->update(['estado' => $estado]);
    }

    /**
     * Obtener el correlativo de una entrega logística
     */
    public static function get_correlativo_entrega(int $id_entrega)
    {
        return SolicitudReabastecimientoEntrega::where('id', $id_entrega)
            ->value('correlativo');
    }
}
