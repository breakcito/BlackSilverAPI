<?php

namespace App\Views\PrestamosAlmacen\Data;

use Illuminate\Support\Facades\DB;

class PrestamosData
{
    /**
     * Obtiene los préstamos que hayan sido solicitados a un almacen
     */
    public static function get_prestamos_por_almacen(int $id_almacen_prestamista, int $mes, int $yearcito): array
    {
        $sql = '
        SELECT
            pa.id AS id_prestamo,
            alm_sol.id AS id_almacen_solicitante,
            pa.correlativo,
            sr.correlativo as solicitud_reabastecimiento,
            pa.fecha_hora_prestamo,
            pa.fecha_limite_devolucion,
            alm_sol.nombre AS almacen_solicitante,    
            CONCAT(e.nombre, " ", e.apellido) AS registrado_por,    
            pa.created_at,
            pa.estado
        FROM
            prestamo_almacen pa
        LEFT JOIN solicitud_reabastecimiento sr ON
            sr.id = pa.id_solicitud_reabastecimiento
        INNER JOIN almacen alm_sol ON
            alm_sol.id = sr.id_almacen_solicitante
        INNER JOIN empleado e ON
            e.id = pa.id_empleado_registro
        WHERE
            pa.id_almacen_prestamista = :id_almacen_prestamista AND 
            MONTH(pa.created_at) = :mes AND 
            YEAR(pa.created_at) = :yearcito
        ORDER BY
            pa.created_at DESC;
        ';

        return DB::select($sql, [
            'id_almacen_prestamista' => $id_almacen_prestamista,
            'mes' => $mes,
            'yearcito' => $yearcito
        ]);
    }
}
