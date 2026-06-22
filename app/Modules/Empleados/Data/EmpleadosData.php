<?php

namespace App\Modules\Empleados\Data;

use Illuminate\Support\Facades\DB;

class EmpleadosData
{
    /**
     * Listar empleados con su empresa y cargo
     */
    public static function get_empleados(?int $id_empresa = null, ?int $id_empleado = null)
    {
        $sql = '
        SELECT
            e.id AS id_empleado,
            e.id_empresa,
            emp_asoc.razon_social AS empresa,
            e.id_cargo,
            car.nombre AS cargo,
            car.id_area,
            a.nombre AS area,
            e.nombre,
            e.apellido,
            e.dni,
            e.ruc,
            e.carnet_extranjeria,
            e.pasaporte,
            e.fecha_nacimiento,
            e.url_foto,
            e.estado
        FROM
            empleado e
        LEFT JOIN empresa emp_asoc ON emp_asoc.id = e.id_empresa
        INNER JOIN cargo car ON car.id = e.id_cargo
        INNER JOIN area a ON a.id = car.id_area
        WHERE e.es_contratista = 0
        ';

        $params = [];

        if ($id_empleado) {
            $sql .= ' AND e.id = :id_empleado';
            $params['id_empleado'] = $id_empleado;
            return DB::selectOne($sql, $params);
        }

        if ($id_empresa !== null) {
            $sql .= ' AND e.id_empresa = :id_empresa';
            $params['id_empresa'] = $id_empresa;
        }

        $sql .= ' ORDER BY e.apellido ASC, e.nombre ASC';

        return DB::select($sql, $params);
    }
}
