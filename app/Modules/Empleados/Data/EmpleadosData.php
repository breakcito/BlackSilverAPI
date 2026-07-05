<?php

namespace App\Modules\Empleados\Data;

use Illuminate\Support\Facades\DB;

class EmpleadosData
{
    /**
     * Convierte una URL (relativa o absoluta) o un path a data URL base64.
     * Si ya es un data URL, lo retorna tal cual. Si el archivo no existe,
     * retorna null.
     */
    private static function logo_a_base64(string $logo): ?string
    {
        if (str_starts_with($logo, 'data:')) {
            return $logo; // ya es data URL
        }
        if (str_starts_with($logo, 'http')) {
            $parsed = parse_url($logo, PHP_URL_PATH);
            $relativePath = ltrim(str_replace('/storage/', '', $parsed ?? ''), '/');
        } else {
            $relativePath = ltrim($logo, '/');
        }

        $fullPath = storage_path('app/public/'.$relativePath);
        if (! file_exists($fullPath)) {
            return null;
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($fullPath));
    }

    /**
     * Listar empleados con su cargo y area.
     *
     * Si el empleado tiene `id_contrato_vigente` (y por tanto `id_cargo = 0),
     * se hace un JOIN adicional a `contrato_trabajo` + `cargo_contrato` para
     * obtener el nombre del cargo y del área del contrato.
     *
     * Las URLs de foto y logo de empresa se convierten a data URL base64
     * para que el frontend (incluyendo `react-pdf` para el fotocheck) pueda
     * renderizarlas sin hacer fetch (evita problemas de CORS, URLs relativas,
     * headers de auth faltantes, etc.).
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
                $row['con_contrato'] = (bool) ($row['con_contrato'] ?? 0);
                $row['url_foto'] = ! empty($row['url_foto'])
                    ? self::logo_a_base64($row['url_foto'])
                    : null;
                $row['empresa_url_logo'] = ! empty($row['empresa_url_logo'])
                    ? self::logo_a_base64($row['empresa_url_logo'])
                    : null;

                return $row;
            })
            ->toArray();
    }
}
