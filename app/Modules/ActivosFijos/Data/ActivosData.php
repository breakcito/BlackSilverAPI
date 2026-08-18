<?php

namespace App\Modules\ActivosFijos\Data;

use Illuminate\Support\Facades\DB;

class ActivosData
{
    /**
     * Listar u obtener un activo
     */
    public static function get_activos(?int $id_activo = null)
    {
        $sql = '
        SELECT
            act.id as id_activo,

            -- datos como producto
            act.id_producto,
            pr.nombre as producto,
            pr.es_auditable,

            -- datos de la categoria a la que pertenece
            -- y determinar si el activo sirve como transporte,
            -- si necesita llevar algun control por odometro u
            -- horometro desde el modulo de Uso
            pr.id_categoria,
            cat.nombre as categoria,
            cat.para_transporte,
            cat.control_por_odometro,
            cat.control_por_horometro,
            cat.control_por_vueltas,

            -- de que marca es
            act.id_marca,
            marc.nombre as marca,

            -- en que mina se encuentra
            act.id_mina,
            mn.nombre as mina,

            -- en que labor se encuentra (opcional)
            act.id_labor,
            lb.nombre as labor,

            -- en que almacen se encuentra
            act.id_almacen,
            alm.nombre as almacen,
            alm.es_principal as en_almacen_principal,

            --
            -- datos propios del activo
            --
            act.codigo, -- puesto por el usuario
            act.correlativo, -- lo genera el sistema
            act.serie_placa,
            act.numero_placa,
            -- datos que los otorga el fabricante
            act.numero_serie,
            act.modelo,
            act.yearcito_modelo,
            act.descripcion, -- descripcion interna o del fabricante
            act.especificaciones, -- JSON con una lista de objetos clave-valor para campos personalizados
            act.evidencias, -- JSON con archivos adjuntos de evidencia

            -- Nuevos campos
            act.id_empleado_responsable,
            CONCAT(emp.nombre, \' \', emp.apellido) as empleado_responsable,
            COALESCE(occ.serie, act.serie_factura_compra) as serie_factura_compra,
            COALESCE(occ.numero, act.numero_factura_compra) as numero_factura_compra,
            act.costo_compra,
            act.costo_promedio_base,
            act.id_orden_compra_recepcion_detalle,
            act.id_orden_compra_detalle,
            ocd.id_orden_compra,
            occr.id_orden_compra_comprobante,

            act.fecha_hora_ingreso,
            act.created_at,
            act.total_horas,
            act.total_kilometros,
            act.total_vueltas,
            act.proxima_advertencia_horas,
            act.proxima_advertencia_kilometros,
            act.proxima_advertencia_vueltas,
            act.intervalo_mantenimiento_horas,
            act.intervalo_mantenimiento_kilometros,
            act.intervalo_mantenimiento_vueltas,
            act.estado
        FROM activo_fijo act
        INNER JOIN producto pr on pr.id = act.id_producto
        INNER JOIN categoria cat on cat.id = pr.id_categoria
        LEFT JOIN marca marc on marc.id = act.id_marca

        -- ubicacion actual, solo puede estar en uno de los 3
        -- o en ninguno de ellos en caso se de de baja
        LEFT JOIN mina mn on mn.id = act.id_mina
        LEFT JOIN labor lb on lb.id = act.id_labor
        LEFT JOIN almacen alm on alm.id = act.id_almacen
        LEFT JOIN empleado emp on emp.id = act.id_empleado_responsable
        LEFT JOIN orden_compra_detalle ocd on ocd.id = act.id_orden_compra_detalle
        LEFT JOIN orden_compra_recepcion_detalle ocrd on ocrd.id = act.id_orden_compra_recepcion_detalle
        LEFT JOIN orden_compra_comprobante_recepcion occr on occr.id_orden_compra_recepcion = ocrd.id_orden_compra_recepcion
        LEFT JOIN orden_compra_comprobante occ on occ.id = occr.id_orden_compra_comprobante
        WHERE 1=1
        ';

        $params = [];

        if ($id_activo != null) {
            $sql .= ' AND act.id = :id_activo';
            $params['id_activo'] = $id_activo;
            $res = DB::selectOne($sql, $params);
            if ($res) {
                if (isset($res->especificaciones) && is_string($res->especificaciones)) {
                    $res->especificaciones = json_decode($res->especificaciones, true);
                }
                if (isset($res->evidencias) && is_string($res->evidencias)) {
                    $res->evidencias = json_decode($res->evidencias, true);
                }
                // Hidratar las labores abastecidas (solo cuando es detalle)
                $res->labores_abastecidas = self::get_labores_abastecidas((int) $res->id_activo);
            }
            return $res;
        }

        $sql .= ' ORDER BY pr.nombre, act.correlativo DESC';
        $results = DB::select($sql, $params);
        if (!empty($results)) {
            // Obtener todos los id_activo para hidratar en una sola pasada
            $ids = array_map(fn($r) => (int) $r->id_activo, $results);
            $laboresPorActivo = self::get_labores_abastecidas_batch($ids);

            foreach ($results as $res) {
                if (isset($res->especificaciones) && is_string($res->especificaciones)) {
                    $res->especificaciones = json_decode($res->especificaciones, true);
                }
                if (isset($res->evidencias) && is_string($res->evidencias)) {
                    $res->evidencias = json_decode($res->evidencias, true);
                }
                $res->labores_abastecidas = $laboresPorActivo[(int) $res->id_activo] ?? [];
            }
        }
        return $results;
    }

    /**
     * Devuelve las labores abastecidas por un activo fijo.
     * @return array Lista de {id_labor, nombre}
     */
    public static function get_labores_abastecidas(int $id_activo): array
    {
        $sql = '
        SELECT laa.id_labor, lb.nombre
        FROM labor_abastecida_activo laa
        INNER JOIN labor lb ON lb.id = laa.id_labor
        WHERE laa.id_activo_fijo = :id_activo
        ORDER BY lb.nombre ASC
        ';
        return DB::select($sql, ['id_activo' => $id_activo]);
    }

    /**
     * Variante batch para evitar N+1 al listar.
     * @return array<int, array> Map id_activo => [ {id_labor, nombre} ]
     */
    public static function get_labores_abastecidas_batch(array $ids_activo): array
    {
        if (empty($ids_activo)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids_activo), '?'));
        $sql = "
        SELECT laa.id_activo_fijo, laa.id_labor, lb.nombre
        FROM labor_abastecida_activo laa
        INNER JOIN labor lb ON lb.id = laa.id_labor
        WHERE laa.id_activo_fijo IN ($placeholders)
        ORDER BY lb.nombre ASC
        ";
        $rows = DB::select($sql, $ids_activo);

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->id_activo_fijo][] = [
                'id_labor' => (int) $r->id_labor,
                'nombre' => $r->nombre,
            ];
        }
        return $map;
    }
}
