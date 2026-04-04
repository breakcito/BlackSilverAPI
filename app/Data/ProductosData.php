<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

class ProductosData
{
    /**
     * Metodo generico para obtener una lista de productos, util para
     * el registro de una solicitud de reabastecimiento, cotizacion
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
            cat.id as id_categoria,
			--
            pr.id_unidad_medida_base,
            uni.nombre as unidad_medida_base,
            uni.abreviatura as unidad_medida_base_abv
        FROM
            producto pr
        INNER JOIN unidad_medida uni ON
            uni.id = pr.id_unidad_medida_base
        WHERE
            pr.estado = "Activo"
        ORDER BY pr.nombre;
        ';

        $params = [];

        return DB::select($sql, $params);
    }
}
