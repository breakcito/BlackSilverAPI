<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;

class AuxData
{

    // Obtener toda la lista de productos junto a la abreviatura de su unidad de medida
    public static function get_productos()
    {
        $sql = '
        SELECT
            pr.id AS id_producto,
            pr.nombre,
            pr.id_unidad_medida_base,
            uni.nombre as unidad_medida_base,
            uni.abreviatura as unidad_medida_base_abv
        FROM
            producto pr
        INNER JOIN unidad_medida uni ON
            uni.id = pr.id_unidad_medida_base
        WHERE 
            pr.estado = "Activo"
        ';

        return DB::select($sql);
    }

    // Obtener la lista de almacenes en las que el empleado
    // solicitante es reesponsable
    public static function get_almacenes(int $id_empleado)
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
            alm.es_principal != 1 AND -- que no sea un almacen principal
            res.id_empleado = :id_empleado AND -- donde el empleado sea responsable
            res.estado = "Activo" -- y su responsabilidad siga vigente
        ';

        return DB::select($sql, ["id_empleado" => $id_empleado]);
    }

    // Listar unidades de medida.
    public static function get_unidades_medida()
    {
        return UnidadMedida::select('id as id_unidad_medida', 'nombre', 'abreviatura')
            ->orderBy('nombre', 'asc')
            ->get();
    }

    // obtener los lotes disponibles del almacen solicitante
    public static function get_lotes_disponibles(int $id_almacen_solicitante, array $id_productos)
    {
        if (empty($id_productos)) {
            return [];
        }

        $inQuery = implode(',', array_fill(0, count($id_productos), '?'));

        $sql = "
            SELECT DISTINCT
                lp.id AS id_lote,
                lp.id_producto,
                lp.id_unidad_medida as id_unidad_medida_lote,
                p.id_unidad_medida_base,
                um_base.abreviatura as unidad_medida_base_abv,
                um_lote.abreviatura AS unidad_medida_lote_abv,
                lp.descripcion,
                lp.correlativo,
                lp.stock_actual,
                lp.contenido_por_presentacion,
                lp.stock_actual_base,
                lp.fecha_hora_ingreso,
                lp.fecha_vencimiento,
                lp.estado,
                p.stock_minimo,
                p.dias_espera_vencimiento,
                lp.created_at, -- <--- AGREGADO PARA EVITAR EL ERROR 3065
                /* Cálculo de días restantes */
                CASE 
                    WHEN lp.fecha_vencimiento IS NOT NULL THEN 
                        DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) 
                    ELSE NULL
                END AS dias_para_vencer,
                /* Determinación del estado de vencimiento */
                CASE
                    WHEN p.es_perecible != 1 THEN 'N/A'
                    WHEN lp.fecha_vencimiento IS NULL THEN 'Sin fecha'
                    WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) < 0 THEN 'Vencido'
                    WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= p.dias_espera_vencimiento THEN 'Por vencer'
                    ELSE 'Vigente'
                END AS estado_vencimiento
            FROM
                lote_producto lp
            INNER JOIN producto p ON
                p.id = lp.id_producto
            LEFT JOIN unidad_medida um_base ON
                um_base.id = p.id_unidad_medida_base
            LEFT JOIN unidad_medida um_lote ON
                um_lote.id = lp.id_unidad_medida
            WHERE
                lp.id_almacen = ? AND
                lp.estado = 'Activo' AND
                lp.stock_actual_base > 0 AND
                lp.id_producto IN ($inQuery)
            HAVING estado_vencimiento != 'Vencido'
            ORDER BY lp.fecha_hora_ingreso, lp.created_at
        ";

        $params = array_merge([$id_almacen_solicitante], $id_productos);
        return DB::select($sql, $params);
    }
}
