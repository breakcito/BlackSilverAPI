<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\SolicitudReabastecimientoEntrega;
use App\Models\SolicitudReabastecimientoEntregaDetalle;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoDetalleEntrega;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoEntrega;

class EntregasData
{
    // Obtener el historial de entregas para una solicitud
    public static function get_historial_entregas(int $id_solicitud)
    {
        return SolicitudReabastecimientoEntrega::get_entregas(
            id_solicitud: $id_solicitud
        );
    }

    // Obtener detalles de una entrega
    public static function get_detalles_entrega(int $id_entrega)
    {
        return SolicitudReabastecimientoEntregaDetalle::get_detalles(id_entrega: $id_entrega);
    }


    // Marcar el detalle de la entrega como recibido
    public static function marcar_entrega_detalle_como_recibido(int $id_entrega_detalle)
    {
        return SolicitudReabastecimientoEntregaDetalle::where('id', $id_entrega_detalle)
            ->update([
                'estado' => EstadoDetalleEntrega::Recibido->value
            ]);
    }

    // Verificar y completar la entrega si corresponde
    public static function verificar_y_completar_entrega(int $id_reabastecimiento_entrega)
    {
        $pendientes = SolicitudReabastecimientoEntregaDetalle::where('id_reabastecimiento_entrega', $id_reabastecimiento_entrega)
            ->where('estado', '!=', EstadoDetalleEntrega::Recibido->value)
            ->where('estado', '!=', EstadoDetalleEntrega::Anulado->value)
            ->count();

        if ($pendientes === 0) {
            SolicitudReabastecimientoEntrega::where('id', $id_reabastecimiento_entrega)
                ->update([
                    'estado' => EstadoEntrega::Recibida->value
                ]);
        }
    }
}
