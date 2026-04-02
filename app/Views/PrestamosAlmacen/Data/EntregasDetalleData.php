<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacenEntregaDetalle;

class EntregasDetalleData
{

    /**
     * Obtiene los detalles de una entrega por prestamo
     */
    public static function get_detalles_entrega(int $id_entrega)
    {
        return PrestamoAlmacenEntregaDetalle::get_detalles(id_entrega: $id_entrega);
    }
}
