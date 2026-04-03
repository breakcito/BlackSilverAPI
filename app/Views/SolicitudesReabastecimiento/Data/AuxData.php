<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

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
}
