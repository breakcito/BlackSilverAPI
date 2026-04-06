<?php

namespace App\Views\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacenRecepcion;
use App\Models\PrestamoAlmacenRecepcionDetalle;

class RecepcionesData
{
    /**
     * Obtener el historial de recepciones de una entrega de préstamo
     */
    public static function get_historial_recepciones(int $id_entrega)
    {
        return PrestamoAlmacenRecepcion::get_recepciones(id_entrega: $id_entrega);
    }

    /**
     * Obtener detalles de una recepción de préstamo
     */
    public static function get_detalles_recepcion(int $id_recepcion)
    {
        return PrestamoAlmacenRecepcionDetalle::get_detalles(id_recepcion: $id_recepcion);
    }
}
