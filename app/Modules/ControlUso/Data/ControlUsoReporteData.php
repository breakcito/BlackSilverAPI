<?php

namespace App\Modules\ControlUso\Data;

use Illuminate\Support\Facades\DB;

class ControlUsoReporteData
{
    /**
     * Obtener TODOS los logs de uso del mes, sin importar el tipo de control ni paginación.
     */
    public static function get_logs_por_mes(int $mes, int $anio)
    {
        $sql = '
        SELECT 
            log.id as id_log,
            log.id_activo_fijo,
            
            act.codigo,
            act.correlativo,
            pr.nombre as producto,
            cat.nombre as categoria,
            cat.control_por_horometro,
            cat.control_por_odometro,
            COALESCE(act_mi.nombre, act_al.nombre, \'SIN UBICACIÓN\') as ubicacion_activo,
            
            log.fecha_hora_inicio_control,
            log.fecha_hora_fin_control,
            log.horometro_inicio,
            log.horometro_fin,
            log.total_horas,
            log.precio_unitario,
            log.costo_total,
            log.observacion,
            
            log.es_para_mina,
            mi.nombre as mina,
            la.nombre as labor,
            cli.razon_social as cliente,
            log.tipo_carga,
            tar.descripcion as tarifa_desc,
            log.cantidad_vueltas,
            log.odometro_inicio,
            log.odometro_fin,
            GREATEST(0, COALESCE(log.odometro_fin, 0) - COALESCE(log.odometro_inicio, 0)) as total_km,
            tm.nombre as tipo_material
        FROM activo_fijo_uso_log log
        INNER JOIN activo_fijo act ON act.id = log.id_activo_fijo
        INNER JOIN producto pr ON pr.id = act.id_producto
        INNER JOIN categoria cat ON cat.id = pr.id_categoria
        LEFT JOIN mina mi ON mi.id = log.id_mina
        LEFT JOIN labor la ON la.id = log.id_labor
        LEFT JOIN cliente cli ON cli.id = log.id_cliente
        LEFT JOIN activo_fijo_tarifa tar ON tar.id = log.id_tarifa
        LEFT JOIN mina act_mi ON act_mi.id = act.id_mina
        LEFT JOIN almacen act_al ON act_al.id = act.id_almacen
        LEFT JOIN tipo_material tm ON tm.id = tar.id_tipo_material
        WHERE MONTH(log.fecha_hora_inicio_control) = :mes 
          AND YEAR(log.fecha_hora_inicio_control) = :anio
        ORDER BY act.correlativo ASC, log.fecha_hora_inicio_control ASC
        ';

        return DB::select($sql, ['mes' => $mes, 'anio' => $anio]);
    }

    /**
     * Obtener los mantenimientos realizados en el mes, para cruzarlos en el excel.
     */
    public static function get_mantenimientos_por_mes(int $mes, int $anio)
    {
        $sql = '
        SELECT 
            m.id,
            m.id_activo_fijo,
            m.fecha_hora_mantenimiento,
            m.tipo_control,
            m.observacion,
            act.total_horas as horometro_actual,
            act.total_kilometros as odometro_actual,
            act.total_vueltas as vueltas_actuales
        FROM mantenimiento_activo_log m
        INNER JOIN activo_fijo act ON act.id = m.id_activo_fijo
        WHERE MONTH(m.fecha_hora_mantenimiento) = :mes 
          AND YEAR(m.fecha_hora_mantenimiento) = :anio
        ORDER BY m.fecha_hora_mantenimiento ASC
        ';

        return DB::select($sql, ['mes' => $mes, 'anio' => $anio]);
    }
}
