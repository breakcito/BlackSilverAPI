<?php

namespace App\Modules\Productos\Data;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ProductosData
{
    /**
     * Listar todos los productos del catálogo con su categoría y unidad de medida
     */
    public static function get_productos(?int $id_producto = null)
    {
        $sql = '
            SELECT
                p.id AS id_producto,
                p.nombre,
                -- 
                p.id_categoria,
                c.nombre as categoria,
                c.clasificacion_bien,
                --
                p.id_unidad_medida_base,
                um.nombre as unidad_medida_base,
                um.abreviatura as unidad_medida_base_abreviatura,
                -- 
                p.prefijo,
                -- 
                p.es_auditable,
                p.es_perecible,
                p.para_mantenimiento,
                -- 
                p.stock_minimo_base,
                p.costo_promedio_base,
                p.costo_promedio_base_log,
                -- 
                p.tiempo_espera_vencimiento,
                p.periodo_espera_vencimiento,
                p.dias_espera_vencimiento,
                -- 
                p.estado
            FROM
                producto p
            INNER JOIN categoria c ON c.id = p.id_categoria
            INNER JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
            WHERE
                1 = 1
        ';

        $params = [];
        if ($id_producto !== null) {
            $sql .= ' AND p.id = :id_producto';
            $params['id_producto'] = $id_producto;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' AND p.estado != :estado_inactivo ORDER BY p.nombre ASC';
        $params['estado_inactivo'] = EstadoBase::Inactivo->value;

        return DB::select($sql, $params);
    }
}
