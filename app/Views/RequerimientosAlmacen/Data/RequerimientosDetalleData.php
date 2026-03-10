<?php

namespace App\Views\RequerimientosAlmacen\Data;

use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
use App\Shared\Enums\RequerimientoAlmacen\EstadoDetalleRequerimiento;
use App\Shared\Enums\RequerimientoAlmacen\TipoOrigenTrazabilidad;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class RequerimientosDetalleData
{
    /**
     * Obtiene los detalles de un requerimiento de almacen
     */
    public static function get_detalles_by_requerimiento(
        ?int $id_requerimiento = null,
        ?int $id_detalle = null
    ) {
        // 1. Definimos la base de la consulta (sin WHERE ni ORDER BY aún)
        $sql = '
    SELECT
        rad.id AS id_requerimiento_almacen_detalle,
        CONCAT(emp.nombre, " ", emp.apellido) AS empleado_atencion,
        pr.nombre AS producto,
        unib.abreviatura AS unidad_medida_base,
        uni.abreviatura AS unidad_medida,
        rad.contenido_por_presentacion,
        rad.cantidad_solicitada,
        rad.cantidad_solicitada_base,
        rad.cantidad_entregada,
        rad.cantidad_entregada_base,
        CASE 
            WHEN rad.cantidad_solicitada_base > 0 THEN 
                ROUND(((rad.cantidad_entregada_base / rad.cantidad_solicitada_base) * 100 ), 0)
            ELSE 0 
        END AS porcentaje_progreso,
        rad.comentario,
        rad.comentario_decision,
        rad.estado
    FROM
        requerimiento_almacen_detalle rad
    INNER JOIN producto pr ON pr.id = rad.id_producto
    LEFT JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
    LEFT JOIN unidad_medida uni ON uni.id = rad.id_unidad_medida
    LEFT JOIN empleado emp ON emp.id = rad.id_empleado_atencion
    WHERE 1=1';

        $params = [];

        if ($id_detalle !== null) {
            $sql .= ' AND rad.id = :id_detalle';
            $params['id_detalle'] = $id_detalle;
            return DB::selectOne($sql, $params);
        }

        if ($id_requerimiento !== null) {
            $sql .= ' AND rad.id_requerimiento_almacen = :id_requerimiento';
            $params['id_requerimiento'] = $id_requerimiento;
        }

        $sql .= ' ORDER BY pr.nombre';

        return DB::select($sql, $params);
    }

    public static function get_detalle_by_id(int $id_detalle)
    {
        return self::get_detalles_by_requerimiento(id_detalle: $id_detalle);
    }

    public static function get_trazabilidad_by_detalle(?int $id_detalle = null, ?int $id_trazabilidad = null)
    {
        $sql = '
            SELECT DISTINCT
                trz.id AS id_trazabilidad,
                CASE
                    WHEN trz.id_empleado IS NOT NULL THEN (
                        SELECT CONCAT(emp.nombre, " ", emp.apellido)
                        FROM empleado emp
                        WHERE emp.id = trz.id_empleado
                    )
                    ELSE NULL
                END AS empleado,
                trz.tipo_origen,
                trz.descripcion,
                trz.created_at,
                trz.estado
            FROM
                requerimiento_almacen_detalle_log trz
            WHERE
            1=1
        ';

        $params = [];
        if ($id_trazabilidad !== null) {
            $sql .= ' AND trz.id = :id_trazabilidad';
            $params['id_trazabilidad'] = $id_trazabilidad;
            return DB::selectOne($sql, $params);
        }

        if ($id_detalle !== null) {
            $sql .= ' AND trz.id_requerimiento_almacen_detalle = :id_detalle';
            $params['id_detalle'] = $id_detalle;
        }

        $sql .= ' ORDER BY trz.created_at DESC';

        return DB::select($sql, $params);
    }

    public static function get_trazabilidad_by_id(int $id_trazabilidad)
    {
        return self::get_trazabilidad_by_detalle(id_trazabilidad: $id_trazabilidad);
    }


    /**
     * Consultas para el registro de un detalle
     */

    /**
     * Obtiene la lista de productos junto a su unidad de medida base
     */
    public static function get_productos()
    {
        $sql = '
        SELECT
            pr.id AS id_producto,
            pr.id_unidad_medida_base,
            pr.nombre,
            uni.abreviatura as unidad_medida_base
        FROM
            producto pr
        INNER JOIN unidad_medida uni ON
            uni.id = pr.id_unidad_medida_base
        WHERE
            pr.estado = "Activo"
        ORDER BY
            pr.nombre;
        ';

        return DB::select($sql, []);
    }

    /**
     * Obtener unidades de medida para elegir en el detalle
     */
    public static function get_unidades_medida()
    {
        return DB::select('
            SELECT id AS id_unidad_medida, nombre, abreviatura
            FROM unidad_medida
            ORDER BY nombre ASC
        ');
    }

    /**
     * Obtener el nuevo correlativo para un requerimiento en 
     * base al almacen de destino
     */
    public static function get_nuevo_correlativo(int $id_almacen_destino)
    {
        return CorrelativoHelper::generar(
            tabla: 'requerimiento_almacen',
            prefijo: 'REQ',
            filtros: ['id_almacen_destino' => $id_almacen_destino]
        );
    }

    /**
     * Crear el detalle de un requerimiento de almacén.
     */
    public static function crear_detalle(
        int $id_requerimiento,
        int $id_producto,
        int $id_unidad_medida,
        float $cantidad,
        float $contenido,
        float $cantidad_base,
        ?string $comentario = null
    ) {
        return RequerimientoAlmacenDetalle::insertGetId([
            'id_requerimiento_almacen'   => $id_requerimiento,
            'id_producto'                => $id_producto,
            'id_unidad_medida'           => $id_unidad_medida,
            'cantidad_solicitada'        => $cantidad,
            'contenido_por_presentacion' => $contenido,
            'cantidad_solicitada_base'   => $cantidad_base,
            'cantidad_entregada'         => 0,
            'cantidad_entregada_base'    => 0,
            'comentario'                 => $comentario,
            'estado'                     => EstadoDetalleRequerimiento::EsperandoAprobacion->value,
        ]);
    }

    /**
     * Registra en la trazbilidad del detalle
     */
    public static function registrar_trazabilidad(
        int $id_detalle,
        int $id_empleado_solicitante
    ) {
        return RequerimientoAlmacenDetalleLog::insertGetId([
            'id_requerimiento_almacen_detalle' => (int) $id_detalle,
            'id_empleado' => $id_empleado_solicitante,
            'tipo_origen' => TipoOrigenTrazabilidad::Solicitud->value,
            'estado' => EstadoDetalleRequerimiento::EsperandoAprobacion->value,
            'descripcion' => EstadoDetalleRequerimiento::EsperandoAprobacion->getGlosa(),
            'created_at' => now(),
        ]);
    }
}
