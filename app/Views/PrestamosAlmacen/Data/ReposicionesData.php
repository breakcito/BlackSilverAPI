<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacenReposicion;
use App\Models\PrestamoAlmacenReposicionDetalle;
use App\Shared\Helpers\ArchivoHelper;

class ReposicionesData
{

    /**
     * Genera un nuevo correlativo para una reposición.
     */
    public static function get_nuevo_correlativo(int $id_almacen)
    {
        return PrestamoAlmacenReposicion::get_nuevo_correlativo($id_almacen);
    }

    /**
     * Obtiene el historial de reposiciones de un préstamo.
     */
    public static function get_reposiciones_by_prestamo(int $id_prestamo_almacen): array
    {
        return PrestamoAlmacenReposicion::get_reposiciones(id_prestamo_almacen: $id_prestamo_almacen);
    }

    /**
     * Registra una nueva reposicion por prestamo
     */
    public static function crear_reposicion(
        int $id_prestamo_almacen,
        int $id_almacen_entrega,
        int $id_empleado_entrega,
        string $correlativo,
        int $numero_correlativo,
        ?string $fecha_hora_reposicion,
        ?string $observacion = null,
        ?array $evidencias = null
    ): int {
        return PrestamoAlmacenReposicion::crear_reposicion(
            id_prestamo_almacen: $id_prestamo_almacen,
            id_almacen_entrega: $id_almacen_entrega,
            id_empleado_entrega: $id_empleado_entrega,
            correlativo: $correlativo,
            numero_correlativo: $numero_correlativo,
            fecha_hora_reposicion: $fecha_hora_reposicion,
            observacion: $observacion,
            evidencias: $evidencias
        );
    }

    /**
     * Metodo que guarda las evidencias de una reposicion
     */
    public static function guardar_evidencias(array $evidencias)
    {
        return ArchivoHelper::guardarArchivos('prestamos_almacen_reposiciones', $evidencias);
    }



    /**
     * ------------------------------------------------
     * METODOS PARA LOS DETALLES DE UNA REPOSICION
     * ------------------------------------------------
     */



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
