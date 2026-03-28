<?php

namespace App\Views\PrestamosAlmacenAtencion\Data;

use Illuminate\Support\Facades\DB;

class PrestamosData
{
    /**
     * Obtiene los préstamos recibidos por un almacén (como prestamista).
     * Filtra por mes/año y estado si se proporcionan.
     */
    public static function get_prestamos_por_almacen(int $id_almacen, string $mes, string $yearcito): array
    {
        return DB::select('
            SELECT
                pa.id AS id_prestamo,
                pa.correlativo,
                pa.numero_correlativo,
                pa.fecha_hora_prestamo,
                pa.fecha_limite_devolucion,
                pa.created_at,
                pa.estado,
                pa.id_almacen_prestamista AS id_almacen_prestamista,
                alm_sol.nombre AS almacen_solicitante,
                alm_sol.id    AS id_almacen_solicitante,
                CONCAT(e.nombre, " ", e.apellido) AS registrado_por,
                sr.correlativo AS solicitud_correlativo,
                (
                    SELECT ra_sol.id_empleado 
                    FROM responsable_almacen ra_sol 
                    WHERE ra_sol.id_almacen = alm_sol.id 
                    AND ra_sol.estado = "Activo" 
                    LIMIT 1
                ) AS id_empleado_recibe_default
            FROM
                prestamo_almacen pa
            INNER JOIN solicitud_reabastecimiento sr ON sr.id = pa.id_solicitud_reabastecimiento
            INNER JOIN almacen alm_sol ON alm_sol.id = sr.id_almacen_solicitante
            INNER JOIN empleado e ON e.id = pa.id_empleado_registro
            WHERE
                pa.id_almacen_prestamista = :id_almacen
                AND MONTH(pa.created_at) = :mes
                AND YEAR(pa.created_at) = :yearcito
            ORDER BY pa.created_at DESC
        ', ['id_almacen' => $id_almacen, 'mes' => $mes, 'yearcito' => $yearcito]);
    }

    /**
     * Obtiene la solicitud de reabastecimiento vinculada a un préstamo.
     */
    public static function get_id_solicitud_by_prestamo(int $id_prestamo)
    {
        return DB::table('prestamo_almacen')
            ->select('id_solicitud_reabastecimiento')
            ->where('id', $id_prestamo)
            ->first();
    }

    /**
     * Obtiene información del almacén solicitante de un préstamo.
     */
    public static function get_almacen_solicitante_by_id(int $id_prestamo)
    {
        return DB::table('prestamo_almacen as pa')
            ->join('solicitud_reabastecimiento as sr', 'sr.id', '=', 'pa.id_solicitud_reabastecimiento')
            ->join('almacen as alm', 'alm.id', '=', 'sr.id_almacen_solicitante')
            ->select('alm.nombre')
            ->where('pa.id', $id_prestamo)
            ->first();
    }

    /**
     * Obtiene la cabecera de un préstamo.
     */
    public static function get_prestamo_header_by_id(int $id_prestamo)
    {
        return DB::table('prestamo_almacen')
            ->where('id', $id_prestamo)
            ->first();
    }
}
