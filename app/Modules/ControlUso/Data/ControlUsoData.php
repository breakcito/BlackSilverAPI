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
            pr.es_auditable,
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
            log.created_at,
            
            -- nuevos datos operativos
            log.es_para_mina,
            log.id_mina,
            mi.nombre as mina,
            log.id_labor,
            la.nombre as labor,
            log.id_cliente,
            cli.razon_social as cliente,
            log.tipo_carga,
            log.id_tarifa,
            tar.descripcion as tarifa_desc,
            log.cantidad_vueltas,
            log.cantidad_sacos,
            tar.distancia_metros as tarifa_distancia_metros,
            mat.nombre as tarifa_material,
            log.odometro_inicio,
            log.odometro_fin,
            GREATEST(0, COALESCE(log.odometro_fin, 0) - COALESCE(log.odometro_inicio, 0)) as total_km
        FROM control_uso_activo log
        INNER JOIN activo_fijo act ON act.id = log.id_activo_fijo
        INNER JOIN producto pr ON pr.id = act.id_producto
        INNER JOIN categoria cat ON cat.id = pr.id_categoria
        LEFT JOIN mina mi ON mi.id = log.id_mina
        LEFT JOIN labor la ON la.id = log.id_labor
        LEFT JOIN cliente cli ON cli.id = log.id_cliente
        LEFT JOIN tarifa_uso_activo tar ON tar.id = log.id_tarifa
        LEFT JOIN tipo_material mat ON mat.id = tar.id_tipo_material
        WHERE 1=1
        ';

        $params = [];

        if ($tipo_control === 'horometro') {
            $sql .= ' AND (log.horometro_inicio IS NOT NULL OR log.horometro_fin IS NOT NULL)';
        } elseif ($tipo_control === 'odometro') {
            $sql .= ' AND (log.odometro_inicio IS NOT NULL OR log.odometro_fin IS NOT NULL)';
        } elseif ($tipo_control === 'vueltas') {
            $sql .= ' AND log.cantidad_vueltas IS NOT NULL';
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
     * Obtener el último registro de uso para un activo específico (horómetro), ordenado por fecha de fin.
     */
    public static function get_ultimo_registro(int $id_activo_fijo)
    {
        $sql = '
        SELECT 
            horometro_fin,
            fecha_hora_fin_control
        FROM control_uso_activo
        WHERE id_activo_fijo = :id_activo_fijo AND horometro_fin IS NOT NULL
        ORDER BY fecha_hora_fin_control DESC, id DESC
        LIMIT 1
        ';

        return DB::selectOne($sql, ['id_activo_fijo' => $id_activo_fijo]);
    }

    /**
     * Obtener el último registro de uso para un activo específico (odómetro), ordenado por fecha de fin.
     */
    public static function get_ultimo_registro_odometro(int $id_activo_fijo)
    {
        $sql = '
        SELECT 
            odometro_fin,
            fecha_hora_fin_control
        FROM control_uso_activo
        WHERE id_activo_fijo = :id_activo_fijo AND odometro_fin IS NOT NULL
        ORDER BY fecha_hora_fin_control DESC, id DESC
        LIMIT 1
        ';

        return DB::selectOne($sql, ['id_activo_fijo' => $id_activo_fijo]);
    }

    /**
     * Obtener el listado de tarifas para un activo.
     */
    public static function get_tarifas(int $id_activo_fijo)
    {
        $sql = '
        SELECT 
            t.id,
            t.id_activo_fijo,
            t.tipo_control,
            t.precio_unitario,
            t.descripcion,
            t.id_tipo_material,
            t.distancia_metros,
            m.nombre as tipo_material,
            t.created_at
        FROM tarifa_uso_activo t
        LEFT JOIN tipo_material m ON m.id = t.id_tipo_material
        WHERE t.id_activo_fijo = :id_activo_fijo
        ORDER BY t.id DESC
        ';

        return DB::select($sql, ['id_activo_fijo' => $id_activo_fijo]);
    }

    /**
     * Obtener el listado de materiales.
     */
    public static function get_materiales()
    {
        $sql = '
        SELECT 
            id,
            nombre,
            created_at
        FROM tipo_material
        ORDER BY nombre ASC
        ';

        return DB::select($sql);
    }
}
