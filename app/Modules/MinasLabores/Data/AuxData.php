<?php

namespace App\Modules\MinasLabores\Data;

use Illuminate\Support\Facades\DB;

class AuxData
{
    /**
     * Concesiones
     */
    public static function get_concesiones()
    {
        $sql = '
        SELECT DISTINCT
            cn.id AS id_concesion,
            cn.nombre
        FROM
            concesion cn
        INNER JOIN contrato_concesion ctr ON
            ctr.id_concesion = cn.id
        INNER JOIN empresa emp ON
            emp.id = ctr.id_empresa
        WHERE
            ctr.estado = "Activo"
        ';

        return DB::select($sql);
    }
}
