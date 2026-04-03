<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacen;

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

    public static function get_correlativo_by_id(int $id_prestamo_almacen)
    {
        return PrestamoAlmacen::where('id_prestamo_almacen', $id_prestamo_almacen)
            ->first(
                [
                    'correlativo',
                ]
            );
    }
}
