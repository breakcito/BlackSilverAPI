<?php

namespace App\Modules\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacenEntregaRecepcion;
use App\Models\PrestamoAlmacenEntregaRecepcionDetalle;

class RecepcionesData
{
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
}
