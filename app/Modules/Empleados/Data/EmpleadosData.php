<?php

namespace App\Modules\Empleados\Data;

use Illuminate\Support\Facades\DB;

class EmpleadosData
{
    /**
     * Listar empleados con su cargo y area.
     *
     * Si el empleado tiene `id_contrato_vigente` (y por tanto `id_cargo = 0`),
     * se hace un JOIN adicional a `contrato_trabajo` + `cargo` + `area` para
     * obtener el nombre del cargo y del área del contrato vigente.
     */
    public static function get_empleados(?int $id_empleado = null)
    {
        $sql = '
        SELECT
            e.id AS id_empleado,
            IFNULL(car.nombre, car_contrato.nombre) AS cargo,
            IFNULL(car.id_area, car_contrato.id_area) AS id_area,
            IFNULL(a.nombre, a_contrato.nombre) AS area,
            e.id_contrato_vigente,
            ct_vig.id_empresa,
            emp_asoc.razon_social AS empresa,
            emp_asoc.url_logo AS empresa_url_logo,
            e.qr_token,
            e.nombre,
            e.apellido,
            e.dni,
            e.genero,
            e.ruc,
            e.carnet_extranjeria,
            e.pasaporte,
            e.fecha_nacimiento,
            e.con_contrato,
            e.direccion,
            e.telefono,
            e.email,
            e.url_foto,
            e.estado
        FROM
            empleado e
        LEFT JOIN cargo car ON car.id = e.id_cargo
        LEFT JOIN area a ON a.id = car.id_area
        LEFT JOIN contrato_trabajo ct_vig ON ct_vig.id = e.id_contrato_vigente
        LEFT JOIN cargo car_contrato ON car_contrato.id = ct_vig.id_cargo
        LEFT JOIN area a_contrato ON a_contrato.id = car_contrato.id_area
        LEFT JOIN empresa emp_asoc ON emp_asoc.id = ct_vig.id_empresa
        WHERE e.es_contratista = 0
        ';

        $params = [];

        if ($id_empleado) {
            $sql .= ' AND e.id = :id_empleado';
            $params['id_empleado'] = $id_empleado;

            return DB::selectOne($sql, $params) ?: (object) [];
        }

        $sql .= ' ORDER BY e.apellido ASC, e.nombre ASC';

        return collect(DB::select($sql, $params))
            ->map(function ($row) {
                $row = (array) $row;
                // Cast manual: la query builder no aplica los $casts del modelo.
                $row['con_contrato'] = (bool) ($row['con_contrato'] ?? 0);

                return $row;
            })
            ->toArray();
    }
}
