<?php

namespace App\Views\RequerimientosAlmacenAtencion\Data;

use App\Models\Labor;
use App\Models\RequerimientoAlmacenEntrega;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class EntregasData
{

    /**
     * Obtener el historial de entregas en base al detalle de un requerimiento
     */
    public static function get_historial_entregas(?int $id_detalle_requerimiento = null, ?int $id_entrega = null)
    {
        $sql = '
        SELECT DISTINCT
            ent.id AS id_requerimiento_almacen_entrega,
            CONCAT(emp_ent.nombre," ",emp_ent.apellido) AS empleado_entrega,
            CONCAT(emp_rec.nombre," ",emp_rec.apellido) AS empleado_recibe,
            ent.correlativo,
            ent.fecha_hora_entrega,
            ent.observacion,
            ent.evidencias,
            ent.created_at,
            ent.estado
        FROM
            requerimiento_almacen_entrega_detalle raed
        INNER JOIN requerimiento_almacen_entrega ent ON
            ent.id = raed.id_requerimiento_almacen_entrega
        LEFT JOIN empleado emp_ent ON
            emp_ent.id = ent.id_empleado_entrega
        LEFT JOIN empleado emp_rec ON
            emp_rec.id = ent.id_empleado_recibe
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_entrega) {
            $sql .= ' AND ent.id = :id_entrega';
            $params['id_entrega'] = $id_entrega;
            return DB::selectOne($sql, $params);
        }

        if ($id_detalle_requerimiento) {
            $sql .= ' AND raed.id_requerimiento_almacen_detalle = :id_detalle_requerimiento';
            $params['id_detalle_requerimiento'] = $id_detalle_requerimiento;
        }

        $sql .= ' ORDER BY ent.correlativo DESC;';

        return DB::select($sql, $params);
    }

    /**
     * Obtener una entrega
     */
    public static function get_entrega_by_id(int $id_entrega)
    {
        return self::get_historial_entregas(id_entrega: $id_entrega);
    }

    /**
     * obtener la lista de almacenes donde el empleado es responsable
     */
    public static function get_almacenes(int $id_empleado): array
    {
        $sql = '
        SELECT DISTINCT
            alm.id AS id_almacen,
            alm.nombre
        FROM
            almacen alm
        INNER JOIN responsable_almacen res ON
            res.id_almacen = alm.id
        WHERE
            alm.estado = "Activo" AND
            alm.es_principal != 1 AND 
            res.estado = "Activo" AND 
            res.id_empleado = :id_empleado
        ';

        return DB::select($sql, ['id_empleado' => $id_empleado]);
    }

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
            ra.id_almacen_destino = 1 AND
            MONTH(ra.created_at) = 3 AND YEAR(ra.created_at) = 2026
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
     * Consultas utiles para el registro de una entrega
     */

    /**
     * Obtener el nuevo correlativo para un entrega en
     * base al almacen de destino
     */
    public static function get_nuevo_correlativo(int $id_almacen_destino)
    {
        return CorrelativoHelper::generar(
            prefijo: 'ENT',
            tabla: 'requerimiento_almacen_entrega',
            alias: 'ent',
            queryModifier: function ($query) {
                // conectamos las entregas con los requerimientos para filtrar por almacen
                $query->join(
                    'requerimiento_almacen as req',
                    'req.id',
                    '=',
                    'ent.id_requerimiento_almacen'
                );
            },
            filtros: ['req.id_almacen_destino' => $id_almacen_destino],
        );
    }

    /**
     * Crear una nueva entrega
     */
    public static function crear_entrega(
        int $id_requerimiento,
        int $id_empleado_entrega,
        int $id_empleado_recibe,
        string $correlativo,
        int $numero_correlativo,
        int $fecha_hora_entrega,
        ?string $observacion = null,
    ) {
        return RequerimientoAlmacenEntrega::insertGetId([
            'id_requerimiento_almacen' => $id_requerimiento,
            'id_empleado_entrega' => $id_empleado_entrega,
            'id_empleado_recibe' => $id_empleado_recibe,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'fecha_hora_entrega' => $fecha_hora_entrega,
            'observacion' => $observacion,
            'created_at' => now(),
            'estado' => 'Procesado'
        ]);
    }

    // obtener la lista de empleados para indicar quien recibe
    public static function get_empleados()
    {
        return DB::select('
        SELECT DISTINCT
            emp.id AS id_empleado,
            CONCAT(emp.nombre, " ", emp.apellido) AS nombre_completo,
            emp.dni,
            emp.path_foto
        FROM
            empleado emp
        WHERE
            emp.estado = "Activo"
        ');
    }
}
