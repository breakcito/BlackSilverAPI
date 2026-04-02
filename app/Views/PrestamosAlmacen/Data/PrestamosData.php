<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacen;
use Illuminate\Support\Facades\DB;

class PrestamosData
{
    /**
     * Obtiene los préstamos que hayan sido solicitados a un almacen
     */
    public static function get_prestamos_por_almacen(int $id_almacen_prestamista, int $mes, int $yearcito): array
    {
        return PrestamoAlmacen::get_prestamos(
            id_almacen_prestamista: $id_almacen_prestamista,
            mes: $mes,
            yearcito: $yearcito
        );
    }
}
