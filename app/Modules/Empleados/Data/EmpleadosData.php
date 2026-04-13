<?php

namespace App\Modules\Empleados\Data;

use App\Models\Empleado;
use App\Models\Labor;
use App\Models\LaborEmpleado;
use App\Shared\Enums\EstadoBase;
use Illuminate\Support\Facades\DB;

class EmpleadosData
{
    /**
     * Listar empleados con su mina y labores asignadas
     */
    public static function get_empleados(?int $id_mina = null, ?int $id_empleado = null)
    {
        $sql = '
        SELECT DISTINCT
            e.id AS id_empleado,
            e.id_mina,
            mn.nombre AS mina,
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
            e.path_foto,
            e.estado,
            COALESCE((
                SELECT GROUP_CONCAT(lab.correlativo ORDER BY lab.correlativo SEPARATOR " | ")
                FROM labor_empleado le
                INNER JOIN labor lab ON lab.id = le.id_labor
                WHERE le.id_empleado = e.id
            ), "Sin asignar") AS labores_asignadas
        FROM
            empleado e
        INNER JOIN mina mn ON mn.id = e.id_mina
        INNER JOIN cargo car ON car.id = e.id_cargo
        INNER JOIN area a ON a.id = car.id_area
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_empleado) {
            $sql .= ' AND e.id = :id_empleado';
            $params['id_empleado'] = $id_empleado;

            return DB::selectOne($sql, $params);
        }

        if ($id_mina !== null) {
            $sql .= ' AND e.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY e.apellido ASC, e.nombre ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener empleado por ID
     */
    public static function get_empleado_by_id(int $id_empleado)
    {
        return self::get_empleados(id_empleado: $id_empleado);
    }

    /**
     * Crear un nuevo empleado con parámetros explícitos
     */
    public static function crear_empleado(
        int $id_mina,
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
            'id_mina'            => $id_mina,
            'id_cargo'           => $id_cargo,
            'nombre'             => $nombre,
            'apellido'           => $apellido,
            'dni'                => $dni,
            'ruc'                => $ruc,
            'carnet_extranjeria' => $carnet_extranjeria,
            'pasaporte'          => $pasaporte,
            'fecha_nacimiento'   => $fecha_nacimiento,
            'path_foto'          => $path_foto,
            'estado'             => EstadoBase::Activo->value,
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
     * Obtener minas activas
     */
    public static function get_minas()
    {
        return DB::select('
            SELECT id AS id_mina, nombre
            FROM mina
            WHERE estado = :estado
            ORDER BY nombre ASC
        ', ['estado' => EstadoBase::Activo->value]);
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

    /**
     * Actualizar la ruta de la foto de un empleado
     */
    public static function actualizar_foto(int $id_empleado, ?string $path_foto): bool
    {
        return (bool) Empleado::where('id', $id_empleado)->update(['path_foto' => $path_foto]);
    }

    // -------------------------------------------------------------------------
    // Métodos para el proceso de asignación de labores al empleado
    // -------------------------------------------------------------------------

    /**
     * Obtener las labores activas de una mina, excluyendo las ya asignadas al empleado.
     * Si no se pasa id_empleado, devuelve todas las labores activas de la mina.
     */
    public static function get_labores_disponibles_mina(int $id_mina, ?int $id_empleado = null)
    {
        $sql = '
        SELECT
            lab.id AS id_labor,
            lab.correlativo,
            lab.nombre
        FROM labor lab
        WHERE lab.id_mina = :id_mina
        AND lab.estado = :estado
        ';

        $params = [
            'id_mina' => $id_mina,
            'estado'  => EstadoBase::Activo->value,
        ];

        if ($id_empleado !== null) {
            $sql .= '
            AND lab.id NOT IN (
                SELECT id_labor FROM labor_empleado WHERE id_empleado = :id_empleado
            )
            ';
            $params['id_empleado'] = $id_empleado;
        }

        $sql .= ' ORDER BY lab.correlativo ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener las labores ya asignadas a un empleado
     */
    public static function get_labores_empleado(int $id_empleado)
    {
        return DB::select('
        SELECT
            le.id AS id_labor_empleado,
            lab.id AS id_labor,
            lab.correlativo,
            lab.nombre
        FROM labor_empleado le
        INNER JOIN labor lab ON lab.id = le.id_labor
        WHERE le.id_empleado = :id_empleado
        ORDER BY lab.correlativo ASC
        ', ['id_empleado' => $id_empleado]);
    }

    /**
     * Verificar si una labor ya está asignada al empleado
     */
    public static function existe_labor_empleado(int $id_empleado, int $id_labor): bool
    {
        return LaborEmpleado::where('id_empleado', $id_empleado)
            ->where('id_labor', $id_labor)
            ->exists();
    }

    /**
     * Asignar una labor a un empleado
     */
    public static function asignar_labor(int $id_empleado, int $id_labor): void
    {
        LaborEmpleado::create([
            'id_empleado' => $id_empleado,
            'id_labor'    => $id_labor,
        ]);
    }

    /**
     * Eliminar todas las labores asignadas a un empleado
     */
    public static function eliminar_labores_empleado(int $id_empleado): void
    {
        LaborEmpleado::where('id_empleado', $id_empleado)->delete();
    }

    /**
     * Obtener la mina de un empleado
     */
    public static function get_mina_empleado(int $id_empleado): ?int
    {
        return Empleado::where('id', $id_empleado)->value('id_mina');
    }

    /**
     * Verificar si una labor pertenece a una mina
     */
    public static function labor_pertenece_a_mina(int $id_labor, int $id_mina): bool
    {
        return Labor::where('id', $id_labor)
            ->where('id_mina', $id_mina)
            ->exists();
    }
}
