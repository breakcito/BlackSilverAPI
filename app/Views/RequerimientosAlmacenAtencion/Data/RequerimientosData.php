<?php

namespace App\Views\RequerimientosAlmacenAtencion\Data;

use App\Models\Labor;
use Illuminate\Support\Facades\DB;

class RequerimientosData
{

    /**
     * Obtiene los requerimientos de almacen por atender/atendidos
     */
    public static function get_resumen_requerimientos(
        int $id_almacen,
        string $mes,
        string $yearcito,
    ) {
        $sql = '
        SELECT
            ra.id AS id_requerimiento,
            ra.id_almacen_destino,
            ra.correlativo,
            ra.observacion,
            m.nombre AS mina,
            CONCAT(emp.nombre, " ", emp.apellido) AS solicitante,
            ra.premura,
            ra.fecha_entrega_requerida,
            ra.estado,
            ra.created_at
        FROM
            requerimiento_almacen ra
        INNER JOIN empleado emp ON emp.id = ra.id_empleado_solicitante
        INNER JOIN mina m ON m.id = ra.id_mina
        WHERE
            ra.id_almacen_destino = :id_almacen_destino AND
            MONTH(ra.created_at) = :mes AND YEAR(ra.created_at) = :yearcito
        ORDER BY 
        	CASE ra.estado
                WHEN "Generado"  THEN 1
                WHEN "En Proceso" THEN 2
                WHEN "Cerrado" THEN 3
                WHEN "Anulado" THEN 4
            	ELSE 5 
            END ASC,
        	ra.created_at DESC
        ';

        $params = [
            'id_almacen_destino' => $id_almacen,
            'mes' => $mes,
            'yearcito' => $yearcito,
        ];

        return DB::select($sql, $params);
    }

    /**
     * Obtiene las labores asociadas a un requerimiento de almacen
     */
    public static function get_labores_by_requerimiento(int $id_requerimiento)
    {
        return Labor::get_labores_by_requerimiento(id_requerimiento: $id_requerimiento);
    }

    /**
     * Actualizar estado del requerimiento
     */
    public static function update_requerimiento_estado(int $id_requerimiento, string $estado)
    {
        return DB::table('requerimiento_almacen')
            ->where('id', $id_requerimiento)
            ->update([
                'estado' => $estado,
                'updated_at' => now()
            ]);
    }
}
