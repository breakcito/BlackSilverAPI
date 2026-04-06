<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacenEntrega;
use App\Models\PrestamoAlmacenEntregaDetalle;

class EntregasData
{

    /**
     * Obtiene el historial de entregas de un préstamo
     */
    public static function get_entregas_por_prestamo(int $id_prestamo): array
    {
        return PrestamoAlmacenEntrega::get_entregas(id_prestamo: $id_prestamo);
    }

    /**
     * Obtiene los detalles de una entrega por prestamo
     */
    public static function get_detalles_entrega(int $id_entrega)
    {
        return PrestamoAlmacenEntregaDetalle::get_detalles(id_entrega: $id_entrega);
    }

    /**
     * Obtiene las recepciones de una entrega específica
     */
    public static function get_recepciones_por_entrega(int $id_entrega)
    {
        return \App\Models\PrestamoAlmacenRecepcion::get_recepciones(id_entrega: $id_entrega);
    }

    /**
     * Obtiene los detalles técnicos de una recepción específica
     */
    public static function get_detalles_recepcion(int $id_recepcion)
    {
        return \App\Models\PrestamoAlmacenRecepcionDetalle::get_detalles(id_recepcion: $id_recepcion);
    }
}
