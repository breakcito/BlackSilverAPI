<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

class UnidadesMedidaData
{
    /**
     * Metodo generico para obtener unidades de medida
     */
    public static function get_unidades(
        ?int $id_unidad_medida = null,
    ) {
        $sql = '
        SELECT 
            id AS id_unidad_medida, 
            nombre, 
            abreviatura
        FROM unidad_medida
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_unidad_medida) {
            $sql .= " AND id = :id_unidad_medida";
            $params['id_unidad_medida'] = $id_unidad_medida;
            return DB::selectOne($sql, $params);
        }

        $sql .= " ORDER BY nombre ASC";
        return DB::select($sql, $params);
    }
}
