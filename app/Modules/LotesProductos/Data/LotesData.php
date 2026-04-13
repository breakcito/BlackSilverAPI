<?php

namespace App\Modules\LotesProductos\Data;

use Illuminate\Support\Facades\DB;

class LotesData
{

    /**
     * Listar lotes de un almacén.
     */
    public static function get_resumen_lotes(?int $id_almacen = null, ?int $id_lote = null)
    {
        $sql = '
        SELECT
            lp.id AS id_lote,
            lp.id_producto,
            lp.id_unidad_medida,
            lp.id_almacen,
            p.nombre as producto,
            um_base.abreviatura as unidad_medida_base,
            c.nombre AS categoria,
            um_lote.abreviatura AS unidad_medida,
            lp.descripcion,
            lp.correlativo,
            lp.stock_actual,
            lp.contenido_por_presentacion,
            lp.stock_actual_base,
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            lp.estado,
            p.es_perecible,
            p.es_fiscalizado,
            p.stock_minimo,
            p.dias_espera_vencimiento,
            /* Cálculo de días restantes */
            CASE 
                WHEN lp.fecha_vencimiento IS NOT NULL THEN 
                    DATEDIFF(lp.fecha_vencimiento,CURRENT_DATE) 
                ELSE NULL
            END AS dias_para_vencer,
            /* Determinación del estado de vencimiento */
            CASE
                WHEN p.es_perecible != 1 THEN "N/A"
                WHEN lp.fecha_vencimiento IS NULL THEN "Sin fecha"
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) < 0 THEN "Vencido"
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= p.dias_espera_vencimiento THEN "Por vencer"
                ELSE "Vigente"
            END AS estado_vencimiento
        FROM
            lote_producto lp
        INNER JOIN producto p ON
            p.id = lp.id_producto
        LEFT JOIN categoria c ON
            c.id = p.id_categoria
        LEFT JOIN unidad_medida um_base ON
            um_base.id = p.id_unidad_medida_base
        LEFT JOIN unidad_medida um_lote ON
            um_lote.id = lp.id_unidad_medida
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_lote !== null) {
            $sql .= ' AND lp.id = :id_lote';
            $params['id_lote'] = $id_lote;

            return DB::selectOne($sql, $params);
        }

        if ($id_almacen !== null) {
            $sql .= ' AND lp.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        $sql .= ' ORDER BY lp.fecha_hora_ingreso DESC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener lote por ID (para retorno post-creación).
     */
    public static function get_lote_by_id(int $id_lote)
    {
        return self::get_resumen_lotes(id_lote: $id_lote);
    }
}
