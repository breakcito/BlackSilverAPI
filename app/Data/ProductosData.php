<?php

namespace App\Data;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ProductosData
{

    /**
     * Listado de productos
     */
    public static function get_productos()
    {
        $sql = '
            SELECT 
                p.id AS id_producto,
                p.nombre as producto,
                p.es_perecible,
                p.es_fiscalizado,
                --
                c.id AS id_categoria,
                c.nombre AS categoria,
                --
                p.id_unidad_medida_base,
                um.nombre AS unidad_medida_base,
                um.abreviatura AS unidad_medida_base_abv
            FROM producto p
            INNER JOIN categoria c ON c.id = p.id_categoria
            INNER JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
            WHERE p.estado = :estado
            ORDER BY p.nombre ASC
        ';

        return DB::select($sql, [
            'estado' => EstadoBase::Activo->value
        ]);
    }
}
