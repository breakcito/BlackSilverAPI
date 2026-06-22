<?php

namespace App\Data;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class CargosData
{
    /**
     * Metodo generico para obtener los cargos
     */
    public static function get_cargos(
        ?int $id_cargo = null,
        ?int $id_area = null,
        ?EstadoBase $estado = null,
    ) {
        $sql = '
        SELECT
            c.id AS id_cargo,
            c.id_area,
            c.nombre,
            c.estado
        FROM cargo c
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_cargo !== null) {
            $sql .= " AND c.id = :id_cargo";
            $params['id_cargo'] = $id_cargo;
            return DB::selectOne($sql, $params);
        }

        if ($id_area !== null) {
            $sql .= " AND c.id_area = :id_area";
            $params['id_area'] = $id_area;
        }

        if ($estado !== null) {
            $sql .= " AND c.estado = :estado";
            $params['estado'] = $estado->value;
        }

        $sql .= " ORDER BY c.nombre;";
        return DB::select($sql, $params);
    }
}
