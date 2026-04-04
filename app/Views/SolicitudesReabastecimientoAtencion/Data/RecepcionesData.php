<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use App\Models\PrestamoAlmacenRecepcion;
use App\Models\PrestamoAlmacenRecepcionDetalle;

class RecepcionesData
{
    public static function get_recepciones_by_entrega(int $id_entrega)
    {
        return PrestamoAlmacenRecepcion::get_recepciones(id_entrega: $id_entrega);
    }

    public static function get_detalles_recepcion(int $id_recepcion)
    {
        return PrestamoAlmacenRecepcionDetalle::get_detalles(id_recepcion: $id_recepcion);
    }
}
