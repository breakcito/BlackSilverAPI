<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use App\Models\SolicitudReabastecimiento;
use Illuminate\Support\Facades\DB;

class SolicitudesData
{

    /**
     * Obtiene las solicitudes de reabastecimiento por atender/atendidos
     */
    public static function get_resumen_solicitudes(
        int $id_almacen,
        string $mes,
        string $yearcito,
    ) {
        $sql = '
        SELECT
            scr.id AS id_solicitud,
            scr.id_almacen_solicitante,
            scr.id_requerimiento_almacen,
            alm.nombre as almacen_solicitante,
            scr.correlativo,
            ra.correlativo as correlativo_requerimiento,
            scr.observacion,
            CONCAT(emp.nombre, " ", emp.apellido) AS solicitante,
            scr.premura,
            scr.fecha_entrega_requerida,
            scr.estado,
            scr.created_at
        FROM
            solicitud_reabastecimiento scr
        INNER JOIN empleado emp ON emp.id = scr.id_empleado_solicitante
        INNER JOIN almacen alm on alm.id = scr.id_almacen_solicitante
        LEFT JOIN requerimiento_almacen ra on ra.id = scr.id_requerimiento_almacen
        WHERE
            scr.id_almacen_solicitante = :id_almacen_solicitante AND
            MONTH(scr.created_at) = :mes AND YEAR(scr.created_at) = :yearcito
        ORDER BY 
        	CASE scr.estado
                WHEN "Generada"  THEN 1
                WHEN "En Proceso" THEN 2
                WHEN "Cerrada" THEN 3
                WHEN "Anulada" THEN 4
            	ELSE 5 
            END ASC,
        	scr.created_at DESC
        ';

        $params = [
            'id_almacen_solicitante' => $id_almacen,
            'mes' => $mes,
            'yearcito' => $yearcito,
        ];

        return DB::select($sql, $params);
    }

    public static function update_solicitud_estado(int $id_solicitud, string $estado)
    {
        return SolicitudReabastecimiento::where('id', $id_solicitud)
            ->update([
                'estado' => $estado
            ]);
    }

    public static function get_correlativo_by_solicitud(int $id_solicitud)
    {
        return SolicitudReabastecimiento::select('correlativo')
            ->where('id', $id_solicitud)
            ->first();
    }
}
