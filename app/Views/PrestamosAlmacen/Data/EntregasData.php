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
}
