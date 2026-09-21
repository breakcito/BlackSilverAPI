<?php

namespace App\Modules\ControlUso\Data;

use Illuminate\Support\Facades\DB;

class ControlUsoReporteData
{
    /**
     * Obtener TODOS los registros de uso del mes.
     */
    public static function get_registros_uso(int $mes, int $anio)
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
            log.id_lote_mineral,
            lm.codigo as lote_mineral,
            cli.razon_social as cliente,
            log.tipo_carga,
            tar.descripcion as tarifa_desc,
            log.cantidad_vueltas,
            log.cantidad_sacos,
            log.odometro_inicio,
            log.odometro_fin,
            GREATEST(0, COALESCE(log.odometro_fin, 0) - COALESCE(log.odometro_inicio, 0)) as total_km,
            tm.nombre as tipo_material,
            log.estado,
            -- Necesarios para el Excel mensual:
            --  - `tipo_turno`: columna TURNO de la hoja
            --  - `uuid_grupo`: agrupar combustible por bloque (combustible
            --    compartido por todos los items del mismo UUID)
            log.tipo_turno,
            log.uuid_grupo
        FROM control_uso_activo log
        INNER JOIN activo_fijo act ON act.id = log.id_activo_fijo
        INNER JOIN producto pr ON pr.id = act.id_producto
        INNER JOIN categoria cat ON cat.id = pr.id_categoria
        LEFT JOIN mina mi ON mi.id = log.id_mina
        LEFT JOIN labor la ON la.id = log.id_labor
        LEFT JOIN lote_mineral lm ON lm.id = log.id_lote_mineral
        LEFT JOIN cliente cli ON cli.id = log.id_cliente
        LEFT JOIN tarifa_uso_activo tar ON tar.id = log.id_tarifa
        LEFT JOIN mina act_mi ON act_mi.id = act.id_mina
        LEFT JOIN almacen act_al ON act_al.id = act.id_almacen
        LEFT JOIN tipo_material tm ON tm.id = tar.id_tipo_material
        WHERE
            MONTH(log.fecha_hora_inicio_control) = :mes AND
            YEAR(log.fecha_hora_inicio_control) = :anio AND
            -- Excluir registros anulados del reporte mensual
            (log.estado IS NULL OR log.estado <> "Anulado")
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
            m.total_horas,
            m.total_kilometros,
            m.total_vueltas,
            m.observacion,
            act.total_horas AS horometro_actual,
            act.total_kilometros AS odometro_actual,
            act.total_vueltas AS vueltas_actuales
        FROM
            mantenimiento_activo m
        INNER JOIN activo_fijo act ON act.id = m.id_activo_fijo
        WHERE MONTH(m.fecha_hora_mantenimiento) = :mes
          AND YEAR(m.fecha_hora_mantenimiento) = :anio
        ORDER BY m.fecha_hora_mantenimiento ASC
        ';

        return DB::select($sql, ['mes' => $mes, 'anio' => $anio]);
    }

    /**
     * Obtener el agregado de CONSUMO DIRECTO de COMBUSTIBLE por `uuid_grupo`
     * para el mes/anio indicado. Se usa en el Excel mensual para mostrar
     * la cantidad de combustible asignada a cada grupo UUID una sola vez
     * (en lugar de repetir el valor en cada fila del grupo).
     *
     * Solo se considera:
     *  - `es_consumo_directo = true` (consumos generados desde Control de Uso)
     *  - `id_producto = 12` (producto combustible hardcoded por requerimiento;
     *    no requiere migracion ni flag nuevo en BD).
     *  - `uuid_control_uso_activo IS NOT NULL` (consumos dentro de un
     *    "Registrar Control por Horometro" con uuid_grupo).
     *
     * Filtro por fecha: se mira la fecha del `control_uso_activo`
     * asociado (`fecha_hora_inicio_control`), NO la fecha del propio
     * consumo (`c.fecha_hora_consumo`). Esto es porque el consumo se
     * inserta con `now()` al momento de registrar el uso, pero el uso
     * al que pertenece puede estar fechado en un mes anterior (ej. un
     * operador registra en septiembre un control de uso fechado en
     * febrero). Si filtraramos por `c.fecha_hora_consumo`, el
     * combustible se perderia del reporte del mes real de operacion.
     *
     * Se usa `EXISTS` en lugar de `INNER JOIN` para NO multiplicar filas
     * del consumo cuando el `uuid_grupo` tiene varios bloques (un
     * "Registrar Control por Horometro" bulk crea N registros de
     * `control_uso_activo` con el mismo uuid_grupo).
     *
     * Devuelve un mapa `{ uuid => { cantidad, unidad, producto } }`.
     */
    public static function get_combustible_por_uuid(int $mes, int $anio)
    {
        $sql = '
        SELECT
            c.uuid_control_uso_activo,
            SUM(c.cantidad_consumo) as cantidad,
            u.abreviatura as unidad,
            p.nombre as producto
        FROM requerimiento_almacen_entrega_detalle_consumo c
        INNER JOIN producto p ON p.id = c.id_producto
        INNER JOIN unidad_medida u ON u.id = c.id_unidad_medida
        WHERE c.es_consumo_directo = 1
          AND c.id_producto = 12
          AND c.uuid_control_uso_activo IS NOT NULL
          AND EXISTS (
              SELECT 1
              FROM control_uso_activo cua
              WHERE cua.uuid_grupo = c.uuid_control_uso_activo
                AND MONTH(cua.fecha_hora_inicio_control) = :mes
                AND YEAR(cua.fecha_hora_inicio_control) = :anio
          )
        GROUP BY c.uuid_control_uso_activo, u.abreviatura, p.nombre
        ';

        $rows = DB::select($sql, ['mes' => $mes, 'anio' => $anio]);

        $out = [];
        foreach ($rows as $r) {
            $uuid = (string) $r->uuid_control_uso_activo;
            $out[$uuid] = [
                'cantidad' => (float) $r->cantidad,
                'unidad' => (string) ($r->unidad ?? ''),
                'producto' => (string) ($r->producto ?? 'Combustible'),
            ];
        }
        return $out;
    }
}
