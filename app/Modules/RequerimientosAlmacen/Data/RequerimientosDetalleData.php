<?php

namespace App\Modules\RequerimientosAlmacen\Data;

use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalleLog;
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
        SELECT DISTINCT
            rad.id AS id_requerimiento_almacen_detalle,
            --
            CONCAT(emp.nombre, " ", emp.apellido) AS empleado_atencion,
            --
            pr.id AS id_producto,
            pr.nombre AS producto,
            pr.stock_minimo,
            --
            -- que producto va a consumir lo que se esta pidiendo: Tractor consume Gasolina
            rad.id_producto_destino,
            p_dest.nombre AS producto_destino,
            --
            pr.id_unidad_medida_base,
            unib.abreviatura AS unidad_medida_base_abv,
            rad.contenido_por_presentacion, -- cuantas unidades base hay en una unidad del detalle del requerimiento
            rad.cantidad_solicitada_base,
            rad.cantidad_entregada_base,
            --
            rad.id_unidad_medida as id_unidad_medida_req, 
            uni.abreviatura AS unidad_medida_req_abv,
            rad.cantidad_solicitada,
            rad.cantidad_entregada,
            --
            CASE 
                WHEN rad.cantidad_solicitada_base > 0 THEN 
                    ROUND(((rad.cantidad_entregada_base / rad.cantidad_solicitada_base) * 100 ), 0)
                ELSE 0 
            END AS porcentaje_progreso,
            --
            rad.comentario,
            rad.comentario_decision,
            rad.estado
        FROM
            requerimiento_almacen_detalle rad
        INNER JOIN producto pr ON pr.id = rad.id_producto
        INNER JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
        INNER JOIN unidad_medida uni ON uni.id = rad.id_unidad_medida
        LEFT JOIN producto p_dest ON p_dest.id = rad.id_producto_destino
        LEFT JOIN empleado emp ON emp.id = rad.id_empleado_atencion
        WHERE 1=1
        ';

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
        return RequerimientoAlmacenDetalleLog::get_logs(
            id_requerimiento_detalle: $id_detalle,
            id_log: $id_trazabilidad
        );
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
            pr.nombre,
            pr.es_fiscalizado,
            pr.es_perecible,
			--
            pr.id_unidad_medida_base,
            uni.nombre as unidad_medida_base,
            uni.abreviatura as unidad_medida_base_abv,
            --
            cat.id as id_categoria,
            cat.nombre as categoria,
            cat.es_consumible,
            --
            (
                SELECT GROUP_CONCAT(cc.id_categoria_consumidora)
                FROM categoria_consumible cc
                WHERE cc.id_categoria_consumible = cat.id
            ) as ids_categorias_consumidoras
        FROM
            producto pr
        INNER JOIN unidad_medida uni ON
            uni.id = pr.id_unidad_medida_base
        INNER JOIN categoria cat ON
            cat.id = pr.id_categoria
        WHERE
            pr.estado = "Activo"
        ORDER BY
            pr.nombre;
        ';

        return DB::select($sql, []);
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
        ?string $comentario = null,
        ?int $id_producto_destino = null
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
            'id_producto_destino'        => $id_producto_destino,
            'estado'                     => EstadoRequerimientoDetalle::EsperandoAprobacion->value,
        ]);
    }

    /**
     * Registra en la trazbilidad del detalle
     */
    public static function registrar_trazabilidad(
        int $id_detalle,
        int $id_empleado_solicitante
    ) {
        return RequerimientoAlmacenDetalleLog::crear_log(
            id_requerimiento_detalle: $id_detalle,
            id_empleado: $id_empleado_solicitante,
            descripcion: EstadoRequerimientoDetalleLog::EsperandoAprobacion->getGlosa(),
            estado: EstadoRequerimientoDetalleLog::EsperandoAprobacion
        );
    }
}
