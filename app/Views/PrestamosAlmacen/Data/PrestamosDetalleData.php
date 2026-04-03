<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacenDetalle;
use App\Models\PrestamoAlmacenDetalleLog;
use Illuminate\Support\Facades\DB;

class PrestamosDetalleData
{
    /**
     * Obtiene los detalles de un préstamo
     */
    public static function get_detalles_prestamo(int $id_prestamo): array
    {
        return PrestamoAlmacenDetalle::get_detalles(id_prestamo: $id_prestamo);
    }

    /**
     * Obtener la trazabilidad de un detalle de préstamo.
     */
    public static function get_detalle_logs(int $id_prestamo_detalle): array
    {
        return PrestamoAlmacenDetalleLog::get_logs(id_prestamo_detalle: $id_prestamo_detalle);
    }

    /**
     * Incrementar cantidades repuestas en el detalle de préstamo
     */
    public static function incrementar_cantidad_repuesta(
        int $id_detalle,
        float $cantidad_repuesta,
        float $cantidad_repuesta_base
    ) {
        return PrestamoAlmacenDetalle::where('id', $id_detalle)
            ->update([
                'cantidad_repuesta' => DB::raw("cantidad_repuesta + $cantidad_repuesta"),
                'cantidad_repuesta_base' => DB::raw("cantidad_repuesta_base + $cantidad_repuesta_base"),
            ]);
    }
}
