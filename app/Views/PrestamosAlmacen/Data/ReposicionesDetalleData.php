<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacenReposicionDetalle;

class ReposicionesDetalleData
{
    /**
     * Obtiene los detalles de una reposición.
     */
    public static function get_detalles_reposicion(int $id_reposicion): array
    {
        return PrestamoAlmacenReposicionDetalle::get_detalles(id_reposicion: $id_reposicion);
    }

    /**
     * Registrar un detalle de una reposicion
     */
    public static function crear_detalle_reposicion(
        int $id_reposicion,
        int $id_prestamo_detalle,
        int $id_lote_producto,
        float $cantidad_base,
        float $cantidad_lote,
        float $cantidad_prestamo,
    ): bool {
        return PrestamoAlmacenReposicionDetalle::crear_detalle(
            $id_reposicion,
            $id_prestamo_detalle,
            $id_lote_producto,
            $cantidad_base,
            $cantidad_lote,
            $cantidad_prestamo,
        );
    }
}
