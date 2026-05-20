<?php

namespace App\Modules\Contratistas\Data;

use App\Models\Empleado;
use App\Models\Labor;
use App\Models\LaborContratista;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ContratistasData
{
    /**
     * Listar contratistas con su mina y labores asignadas
     */
    public static function get_contratistas(?int $id_mina = null, ?int $id_contratista = null)
    {
        $sql = '
        SELECT DISTINCT
            c.id AS id_contratista,
            c.id_mina,
            COALESCE(mn.nombre, "No aplica") AS mina,
            c.nombre,
            c.apellido,
            c.dni,
            c.ruc,
            c.carnet_extranjeria,
            c.pasaporte,
            c.fecha_nacimiento,
            c.path_foto,
            c.estado,
            COALESCE((
                SELECT GROUP_CONCAT(lab.correlativo ORDER BY lab.correlativo SEPARATOR " | ")
                FROM labor_contratista lc
                INNER JOIN labor lab ON lab.id = lc.id_labor
                WHERE lc.id_empleado_contratista = c.id
            ), "No aplica") AS labores_asignadas,
            (
                SELECT GROUP_CONCAT(lc.id_labor)
                FROM labor_contratista lc
                WHERE lc.id_empleado_contratista = c.id
            ) AS ids_labor_asignadas
        FROM
            empleado c
        LEFT JOIN mina mn ON mn.id = c.id_mina
        WHERE c.es_contratista = 1
        ';

        $params = [];

        if ($id_contratista) {
            $sql .= ' AND c.id = :id_contratista';
            $params['id_contratista'] = $id_contratista;

            return DB::selectOne($sql, $params);
        }

        if ($id_mina !== null) {
            $sql .= ' AND c.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY c.apellido ASC, c.nombre ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener contratista por ID
     */
    public static function get_contratista_by_id(int $id_contratista)
    {
        return self::get_contratistas(id_contratista: $id_contratista);
    }

    /**
     * Crear un nuevo contratista
     */
    public static function crear_contratista(
        ?int $id_mina,
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
            'id_cargo'           => null,
            'es_contratista'     => 1,
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
     * Verificar si ya existe un contratista con el mismo DNI
     */
    public static function existe_dni(string $dni): bool
    {
        return Empleado::where('dni', $dni)->exists();
    }

    /**
     * Actualizar la ruta de la foto de un contratista
     */
    public static function actualizar_foto(int $id_contratista, ?string $path_foto): bool
    {
        return (bool) Empleado::where('id', $id_contratista)->update(['path_foto' => $path_foto]);
    }

    /**
     * Obtener las labores activas de una mina, excluyendo las ya asignadas al contratista.
     */
    public static function get_labores_disponibles_mina(int $id_mina, ?int $id_contratista = null)
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

        if ($id_contratista !== null) {
            $sql .= '
            AND lab.id NOT IN (
                SELECT id_labor FROM labor_contratista WHERE id_empleado_contratista = :id_contratista
            )
            ';
            $params['id_contratista'] = $id_contratista;
        }

        $sql .= ' ORDER BY lab.correlativo ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener las labores ya asignadas a un contratista
     */
    public static function get_labores_contratista(int $id_contratista)
    {
        return DB::select('
        SELECT
            lc.id AS id_labor_contratista,
            lab.id AS id_labor,
            lab.correlativo,
            lab.nombre
        FROM labor_contratista lc
        INNER JOIN labor lab ON lab.id = lc.id_labor
        WHERE lc.id_empleado_contratista = :id_contratista
        ORDER BY lab.correlativo ASC
        ', ['id_contratista' => $id_contratista]);
    }

    /**
     * Asignar una labor a un contratista
     */
    public static function asignar_labor(int $id_contratista, int $id_labor): void
    {
        LaborContratista::create([
            'id_empleado_contratista' => $id_contratista,
            'id_labor'    => $id_labor,
        ]);
    }

    /**
     * Eliminar todas las labores asignadas a un contratista
     */
    public static function eliminar_labores_contratista(int $id_contratista): void
    {
        LaborContratista::where('id_empleado_contratista', $id_contratista)->delete();
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
