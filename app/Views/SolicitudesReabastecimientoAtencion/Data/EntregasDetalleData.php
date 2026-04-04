<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use App\Models\SolicitudReabastecimientoEntregaDetalle;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoDetalleEntrega;
use Illuminate\Support\Facades\DB;

class EntregasDetalleData
{

    /**
     * Crear un detalle de  entrega
     */
    public static function crear_detalle_entrega(
        int $id_entrega,
        int $id_solicitud_detalle,
        int $id_lote,
        float $cantidad_base,
        float $cantidad_lote,
        float $cantidad_solicitud,
    ) {
        return SolicitudReabastecimientoEntregaDetalle::insertGetId([
            'id_reabastecimiento_entrega' => $id_entrega,
            'id_solicitud_reabastecimiento_detalle' => $id_solicitud_detalle,
            'id_lote_producto' => $id_lote,
            'cantidad_base' => $cantidad_base,
            'cantidad_lote' => $cantidad_lote,
            'cantidad_solicitud' => $cantidad_solicitud,
            'estado' => EstadoDetalleEntrega::Entregado->value,
        ]);
    }


    /**
     * Obtener los detalles de una entrega
     */
    public static function get_detalles_entrega(?int $id_entrega = null, ?int $id_detalle_entrega = null)
    {
        return SolicitudReabastecimientoEntregaDetalle::get_detalles(
            id_entrega: $id_entrega,
            id_detalle_entrega: $id_detalle_entrega
        );
    }

    /**
     * Obtener un detalle de entrega específico
     */
    public static function get_detalle_entrega_by_id(int $id_detalle_entrega)
    {
        return self::get_detalles_entrega(id_detalle_entrega: $id_detalle_entrega);
    }
}
