<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacenEntrega;

class EntregasData
{

    /**
     * Obtiene el historial de entregas de un préstamo con sus detalles.
     */
    public static function get_entregas_por_prestamo(int $id_prestamo): array
    {
        return PrestamoAlmacenEntrega::get_entregas(id_prestamo: $id_prestamo);
    }
}
