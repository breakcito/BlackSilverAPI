<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Data;

use App\Models\PrestamoAlmacen;
use App\Models\PrestamoAlmacenDetalle;
use App\Models\PrestamoAlmacenDetalleLog;
use App\Shared\Enums\PrestamoAlmacen\EstadoDetallePrestamo;

class PrestamosData
{
    /**
     * ----------------------------------------
     * Metodos para la cabecera del prestamo
     * ----------------------------------------
     */

    public static function get_nuevo_correlativo(int $id_almacen_prestamista)
    {
        return PrestamoAlmacen::get_nuevo_correlativo($id_almacen_prestamista);
    }

    public static function get_prestamos_por_solicitud(int $id_solicitud_reabastecimiento)
    {
        return PrestamoAlmacen::get_prestamos(
            id_solicitud_rebastecimiento: $id_solicitud_reabastecimiento
        );
    }

    public static function get_prestamo_por_id(int $id_prestamo)
    {
        return PrestamoAlmacen::get_prestamos(
            id_prestamo: $id_prestamo
        );
    }

    public static function crear_prestamo(
        int $id_solicitud_reabastecimiento,
        int $id_almacen_solicitante,
        int $id_almacen_prestamista,
        int $id_empleado_registro,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_prestamo,
        ?string $fecha_limite_devolucion,
        ?string $observacion
    ): int {
        return PrestamoAlmacen::crear_prestamo(
            id_solicitud_reabastecimiento: $id_solicitud_reabastecimiento,
            id_almacen_solicitante: $id_almacen_solicitante,
            id_almacen_prestamista: $id_almacen_prestamista,
            id_empleado_registro: $id_empleado_registro,
            correlativo: $correlativo,
            numero_correlativo: $numero_correlativo,
            fecha_hora_prestamo: $fecha_hora_prestamo,
            fecha_limite_devolucion: $fecha_limite_devolucion,
            observacion: $observacion
        );
    }



    /**
     * ----------------------------------------
     * Metodos para el detalle del prestamo
     * ----------------------------------------
     */



    public static function get_detalles_por_prestamo(int $id_prestamo)
    {
        return PrestamoAlmacenDetalle::get_detalles(id_prestamo: $id_prestamo);
    }

    public static function crear_detalle(
        int $id_prestamo,
        int $id_solicitud_reabastecimiento_detalle,
        int $id_producto,
        int $id_unidad_medida,
        float $contenido_por_presentacion,
        float $cantidad_solicitada,
        float $cantidad_solicitada_base,
        ?string $comentario
    ): int {
        return PrestamoAlmacenDetalle::crear_detalle(
            id_prestamo_almacen: $id_prestamo,
            id_solicitud_reabastecimiento_detalle: $id_solicitud_reabastecimiento_detalle,
            id_producto: $id_producto,
            id_unidad_medida: $id_unidad_medida,
            contenido_por_presentacion: $contenido_por_presentacion,
            cantidad_solicitada: $cantidad_solicitada,
            cantidad_solicitada_base: $cantidad_solicitada_base,
            comentario: $comentario,
        );
    }

    public static function crear_detalle_log(
        int $id_prestamo_detalle,
        int $id_empleado,
    ) {
        return PrestamoAlmacenDetalleLog::crear_log(
            id_prestamo_almacen_detalle: $id_prestamo_detalle,
            id_empleado: $id_empleado,
            descripcion: EstadoDetallePrestamo::EsperandoAprobacion->getGlosa(),
            estado: EstadoDetallePrestamo::EsperandoAprobacion,
        );
    }
}
