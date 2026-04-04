<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\PrestamoAlmacenEntrega;
use App\Models\PrestamoAlmacenEntregaDetalle;
use App\Models\SolicitudReabastecimientoEntrega;
use App\Models\SolicitudReabastecimientoEntregaDetalle;

class EntregasData
{
    // Obtener el historial de entregas por logistica para una solicitud
    public static function get_historial_entregas_logistica(int $id_solicitud)
    {
        return SolicitudReabastecimientoEntrega::get_entregas(
            id_solicitud: $id_solicitud
        );
    }

    // Obtener detalles de una entrega por logistica
    public static function get_detalles_entrega_logistica(int $id_entrega)
    {
        return SolicitudReabastecimientoEntregaDetalle::get_detalles(id_entrega: $id_entrega);
    }

    // Obtener el historial de entregas por prestamo para una solicitud
    public static function get_historial_entregas_prestamo(int $id_solicitud)
    {
        return PrestamoAlmacenEntrega::get_entregas(
            id_solicitud_reabastecimiento: $id_solicitud
        );
    }

    // Obtener detalles de una entrega por prestamo
    public static function get_detalles_entrega_prestamo(int $id_entrega)
    {
        return PrestamoAlmacenEntregaDetalle::get_detalles(id_entrega: $id_entrega);
    }
}
