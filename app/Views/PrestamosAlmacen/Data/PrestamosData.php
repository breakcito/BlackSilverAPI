<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacen;
use App\Models\PrestamoAlmacenDetalle;
use App\Models\PrestamoAlmacenDetalleLog;
use App\Shared\Enums\PrestamoAlmacen\EstadoDetallePrestamo;
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

    public static function get_correlativo_by_id(int $id_prestamo_almacen)
    {
        return PrestamoAlmacen::where('id', $id_prestamo_almacen)
            ->first(
                [
                    'correlativo',
                ]
            );
    }



    /**
     * ------------------------------------------------
     * METODOS PARA LOS DETALLES DE UN PRESTAMO
     * ------------------------------------------------
     */



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

    /**
     * Insertar log de trazabilidad en el detalle de préstamo
     */
    public static function crear_log(
        int $id_prestamo_detalle,
        int $id_empleado,
        string $glosa
    ) {
        return PrestamoAlmacenDetalleLog::crear_log(
            id_prestamo_almacen_detalle: $id_prestamo_detalle,
            id_empleado: $id_empleado,
            descripcion: $glosa,
            estado: EstadoDetallePrestamo::EnReposicion,
        );
    }
}
