<?php

namespace App\Views\Empleados\Data;

use App\Models\Empleado;
use App\Shared\Enums\EstadoBase;
use Illuminate\Support\Facades\DB;

class EmpleadosData
{
    /**
     * Listar empleados filtrados por las empresas asociadas al usuario
     */
    public static function get_empleados(int $id_usuario, ?int $id_empresa = null, ?int $id_empleado = null)
    {
        $sql = '
        SELECT DISTINCT
            e.id AS id_empleado,
            emp.nombre_comercial AS empresa,
            car.nombre AS cargo,
            a.nombre AS area,
            e.nombre,
            e.apellido,
            e.dni,
            e.ruc,
            e.carnet_extranjeria,
            e.pasaporte,
            e.fecha_nacimiento,
            e.path_foto,
            e.estado
        FROM
            empleado e
        INNER JOIN empresa emp ON emp.id = e.id_empresa
        INNER JOIN cargo car ON car.id = e.id_cargo
        INNER JOIN area a ON a.id = car.id_area
        INNER JOIN usuario_empresa usu ON usu.id_empresa = emp.id
        WHERE
            usu.id_usuario = :id_usuario
        ';

        $params = ['id_usuario' => $id_usuario];

        if ($id_empresa) {
            $sql .= ' AND e.id_empresa = :id_empresa';
            $params['id_empresa'] = $id_empresa;
        }

        if ($id_empleado) {
            $sql .= ' AND e.id = :id_empleado';
            $params['id_empleado'] = $id_empleado;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY emp.nombre_comercial ASC, e.apellido ASC, e.nombre ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener empleado por ID
     */
    public static function get_empleado_by_id(int $id_usuario, int $id_empleado)
    {
        return self::get_empleados($id_usuario, null, $id_empleado);
    }

    /**
     * Crear un nuevo empleado con parámetros explícitos
     */
    public static function crear_empleado(
        int $id_empresa,
        int $id_cargo,
        string $nombre,
        string $apellido,
        ?string $dni,
        ?string $ruc,
        ?string $carnet_extranjeria,
        ?string $pasaporte,
        ?string $fecha_nacimiento,
        ?string $path_foto
    ) {
        return Empleado::insertGetId([
            'id_empresa' => $id_empresa,
            'id_cargo' => $id_cargo,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => $dni,
            'ruc' => $ruc,
            'carnet_extranjeria' => $carnet_extranjeria,
            'pasaporte' => $pasaporte,
            'fecha_nacimiento' => $fecha_nacimiento,
            'path_foto' => $path_foto,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar si ya existe un empleado con el mismo DNI
     */
    public static function existe_dni(string $dni): bool
    {
        return Empleado::where('dni', $dni)->exists();
    }

    /**
     * Obtener empresas asociadas al usuario
     */
    public static function get_empresas(int $id_usuario)
    {
        return DB::select('
            SELECT DISTINCT e.id AS id_empresa, e.nombre_comercial, e.razon_social
            FROM empresa e
            INNER JOIN usuario_empresa ue ON ue.id_empresa = e.id
            WHERE ue.id_usuario = :id_usuario
        ', ['id_usuario' => $id_usuario]);
    }

    /**
     * Obtener todas las áreas activas
     */
    public static function get_areas()
    {
        return DB::select('SELECT id AS id_area, nombre FROM area WHERE estado = "Activo" ORDER BY nombre ASC');
    }

    /**
     * Obtener cargos por área
     */
    public static function get_cargos_by_area(int $id_area)
    {
        return DB::select('
            SELECT id AS id_cargo, nombre 
            FROM cargo 
            WHERE id_area = :id_area AND estado = "Activo" 
            ORDER BY nombre ASC
        ', ['id_area' => $id_area]);
    }
}
