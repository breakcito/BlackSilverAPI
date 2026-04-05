<?php

namespace App\Views\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacenEntrega;
use App\Models\PrestamoAlmacenEntregaDetalle;
use Illuminate\Support\Facades\DB;

class EntregasData
{
    /**
     * Obtener el historial de entregas de un préstamo
     */
    public static function get_entregas_by_prestamo(int $id_prestamo)
    {
        return PrestamoAlmacenEntrega::get_entregas(id_prestamo: $id_prestamo);
    }

    /**
     * Obtener los detalles de una entrega específica
     */
    public static function get_detalles_entrega(int $id_entrega)
    {
        return PrestamoAlmacenEntregaDetalle::get_detalles(id_entrega: $id_entrega);
    }
}
