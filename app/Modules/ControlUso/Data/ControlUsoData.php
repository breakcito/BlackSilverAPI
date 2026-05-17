<?php

namespace App\Modules\ControlUso\Data;

use Illuminate\Support\Facades\DB;

/**
 * Clase que interactúa directamente con la base de datos para realizar
 * consultas específicas del módulo de Control de Uso.
 */
class ControlUsoData
{
    /**
     * Obtener el listado de logs de uso con filtros de mes, año y tipo de control.
     */
    public static function get_logs(?string $tipo_control = 'horometro', ?int $mes = null, ?int $anio = null)
    {
        $sql = '
        SELECT 
            log.id as id_log,
            log.id_activo_fijo,
            
            -- datos del activo
            act.codigo,
            act.correlativo,
            pr.nombre as producto,
            cat.nombre as categoria,
            cat.control_por_horometro,
            cat.control_por_odometro,
            
            -- datos de control
            log.fecha_hora_inicio_control,
            log.fecha_hora_fin_control,
            log.horometro_inicio,
            log.horometro_fin,
            log.total_horas,
            log.precio_unitario,
            log.costo_total,
            log.observacion,
            log.created_at
        FROM activo_fijo_uso_log log
        INNER JOIN activo_fijo act ON act.id = log.id_activo_fijo
        INNER JOIN producto pr ON pr.id = act.id_producto
        INNER JOIN categoria cat ON cat.id = pr.id_categoria
        WHERE 1=1
        ';

        $params = [];

        if ($tipo_control === 'horometro') {
            $sql .= ' AND cat.control_por_horometro = 1';
        } elseif ($tipo_control === 'odometro') {
            $sql .= ' AND cat.control_por_odometro = 1';
        }

        if ($mes !== null) {
            $sql .= ' AND MONTH(log.fecha_hora_inicio_control) = :mes';
            $params['mes'] = $mes;
        }

        if ($anio !== null) {
            $sql .= ' AND YEAR(log.fecha_hora_inicio_control) = :anio';
            $params['anio'] = $anio;
        }

        $sql .= ' ORDER BY log.fecha_hora_inicio_control DESC, log.id DESC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener el último registro de uso para un activo específico, ordenado por fecha de fin.
     */
    public static function get_ultimo_registro(int $id_activo_fijo)
    {
        $sql = '
        SELECT 
            horometro_fin,
            fecha_hora_fin_control
        FROM activo_fijo_uso_log
        WHERE id_activo_fijo = :id_activo_fijo
        ORDER BY fecha_hora_fin_control DESC, id DESC
        LIMIT 1
        ';

        return DB::selectOne($sql, ['id_activo_fijo' => $id_activo_fijo]);
    }
}
