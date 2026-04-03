<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacenDetalleLog;
use App\Shared\Enums\PrestamoAlmacen\EstadoDetallePrestamo;

class PrestamosDetalleLogData
{
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
