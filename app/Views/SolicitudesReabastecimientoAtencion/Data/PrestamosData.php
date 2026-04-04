<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use Illuminate\Support\Facades\DB;
use App\Models\PrestamoAlmacen;

class PrestamosData
{
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
     * Obtiene los almacenes que tienen stock de los productos solicitados
     */
    public static function get_almacenes_con_stock_multiple_productos(int $id_almacen_excluido, array $ids_productos)
    {
        // Validamos que el array no venga vacío para evitar errores de sintaxis en SQL
        if (empty($ids_productos)) {
            return [];
        }

        // Creamos un string con tantos "?" como IDs haya en el array: "?, ?, ?"
        $placeholders = implode(',', array_fill(0, count($ids_productos), '?'));

        $sql = "
        SELECT
            alm.id AS id_almacen,
            alm.nombre
        FROM
            almacen alm
        INNER JOIN 
            lote_producto lot ON lot.id_almacen = alm.id
        WHERE
            alm.es_principal = 0 AND 
            alm.estado = 'Activo' AND
            alm.id != ? AND -- excluir a un almacen
            lot.id_producto IN ($placeholders) AND
            (lot.fecha_vencimiento > NOW() OR lot.fecha_vencimiento IS NULL) -- aceptar fechas nulas pero no aceptar vencidos
        GROUP BY
            alm.id,
            alm.nombre
        HAVING
            SUM(lot.stock_actual_base) > 0;
        ";

        // Unimos el ID excluido con el array de IDs de productos para enviarlos en orden
        $bindings = array_merge([$id_almacen_excluido], $ids_productos);

        return DB::select($sql, $bindings);
    }
}
