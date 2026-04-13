<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Data;

use App\Models\PrestamoAlmacenEntregaRecepcion;
use App\Models\PrestamoAlmacenEntregaRecepcionDetalle;

class RecepcionesData
{
    public static function get_recepciones_by_entrega(int $id_entrega)
    {
        return PrestamoAlmacenEntregaRecepcion::get_recepciones(id_entrega: $id_entrega);
    }

    public static function get_detalles_recepcion(int $id_recepcion)
    {
        return PrestamoAlmacenEntregaRecepcionDetalle::get_detalles(id_recepcion: $id_recepcion);
    }
}
