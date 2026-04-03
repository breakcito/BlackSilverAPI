<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

class EmpleadosData
{
    /**
     * Obtener listado simple de empleados
     */
    public static function get_empleados(): array
    {
        $sql = '
        SELECT DISTINCT
            emp.id AS id_empleado,
            CONCAT(emp.nombre, " ", emp.apellido) AS nombre_completo,
            emp.path_foto
        FROM
            empleado emp
        WHERE
            emp.estado = "Activo"
        ';

        return DB::select($sql);
    }
}
