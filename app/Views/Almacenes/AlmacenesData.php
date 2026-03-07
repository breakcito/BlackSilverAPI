<?php

namespace App\Views\Almacenes;

use App\Models\Almacen;
use App\Models\AlmacenMina;
use App\Models\ResponsableAlmacen;
use App\Shared\Enums\EstadoBase;
use Illuminate\Support\Facades\DB;

class AlmacenesData
{
    /**
     * Listar un resumen de los almacenes
     * @param mixed $id_almacen
     */
    public static function get_almacenes(?int $id_almacen = null)
    {
        $sql = '
        SELECT
            a.id AS id_almacen,
            a.nombre,
            a.descripcion,
            a.es_principal,
            a.estado,
            (
                SELECT 
                    CONCAT(emp.nombre, " ", emp.apellido)
                FROM responsable_almacen ra
                INNER JOIN empleado emp ON emp.id = ra.id_empleado
                WHERE 
                    ra.id_almacen = a.id AND 
                    ra.estado = "Activo"
                LIMIT 1
            ) AS responsable_actual,
            (
                SELECT 
                    COUNT(*)
                FROM almacen_mina am
                WHERE 
                    am.id_almacen = a.id
            ) AS minas_count -- a cuantas minas abastece
        FROM
            almacen a
        WHERE
            1 = 1
        ';

        $params = [];
        if ($id_almacen !== null) {
            $sql .= ' AND a.id = :id_almacen';
            $params['id_almacen'] = $id_almacen;
            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY a.es_principal DESC, a.nombre ASC';
        return DB::select($sql, $params);
    }

    /**
     * Obtener datos de un almacen
     * @param int $id_almacen
     */
    public static function get_almacen_by_id(int $id_almacen)
    {
        return self::get_almacenes(id_almacen: $id_almacen);
    }

    /**
     * Helper para registrar un almacen
     * @param string $nombre
     * @param mixed $descripcion
     * @param bool $es_principal
     */
    public static function crear_almacen(string $nombre, ?string $descripcion = null, bool $es_principal)
    {
        return Almacen::insertGetId([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'es_principal' => $es_principal,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar si ya existe un almacen activo o inactivo con el mismo nombre
     * @param string $nombre
     */
    public static function verificar_nombre_duplicado(string $nombre)
    {
        return Almacen::where('nombre', $nombre)
            ->where('estado', [EstadoBase::Activo->value, EstadoBase::Inactivo->value])
            ->exists();
    }

    /**
     * Obtener el historial de responsables de un almacen
     * @param mixed $id_almacen
     * @param mixed $id_responsable
     */
    public static function get_historial_responsables(?int $id_almacen = null, ?int $id_responsable = null)
    {
        $sql = '
        SELECT
            ra.id AS id_responsable_almacen,
            ra.id_empleado,
            CONCAT(emp.nombre, " ", emp.apellido) as empleado
            ra.fecha_inicio,
            ra.fecha_fin,
            ra.estado
        FROM
            responsable_almacen ra
        INNER JOIN empleado emp ON emp.id = ra.id_empleado
        WHERE
            1 = 1
        ';

        $params = [];

        // Si se quiere obtener por id solo retornamos uno
        if ($id_responsable != null) {
            $sql .= ' AND ra.id = :id_responsable_almacen';
            $params['id_responsable_almacen'] = $id_responsable;
            return DB::selectOne($sql, $params);
        }

        if ($id_almacen != null) {
            $sql .= ' AND ra.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        $sql .= ' ORDER BY ra.fecha_inicio DESC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener los datos de un responsable de almacen
     * @param int $id_responsable
     */
    public static function get_responsable_by_id(int $id_responsable)
    {
        return self::get_historial_responsables(id_responsable: $id_responsable);
    }

    /**
     * Asignar la fecha de fin de responsabilidad de los responsables de un almacen
     * @param int $id_almacen
     * @param string $fecha_fin
     */
    public static function update_fecha_fin_responsabilidad(int $id_almacen, string $fecha_fin)
    {
        ResponsableAlmacen::where('id_almacen', $id_almacen)
            ->where('estado', EstadoBase::Activo->value) // solo responsables activos
            ->update([
                'fecha_fin' => $fecha_fin, // fecha final
                'estado' => EstadoBase::Inactivo->value, // se inactiva
            ]);
    }

    /**
     * Asignar un nuevo responsable de almacen
     * @param int $id_almacen
     * @param int $id_empleado
     * @param string $fecha_inicio
     */
    public static function nuevo_responsable(
        int $id_almacen,
        int $id_empleado,
        string $fecha_inicio
    ) {
        return ResponsableAlmacen::insertGetId([
            'id_almacen' => $id_almacen,
            'id_empleado' => $id_empleado,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => null,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Listar las minas que abstece un almacen
     * @param mixed $id_almacen
     * @param mixed $id_almacen_mina
     */
    public static function get_minas_abastecidas(?int $id_almacen = null, ?int $id_almacen_mina = null)
    {
        $sql = '
        SELECT
            am.id as id_almacen_mina,
            m.nombre AS mina,
            c.nombre AS concesion
        FROM
            almacen_mina am
        INNER JOIN mina m ON m.id = am.id_mina
        INNER JOIN concesion c ON c.id = m.id_concesion
        WHERE
            1 = 1
        ';

        $params = [];

        // Si solo se quiere obtener una asignacion
        if ($id_almacen_mina) {
            $sql .= ' AND am.id = :id_almacen_mina';
            $params['id_almacen_mina'] = $id_almacen_mina;
            return DB::selectOne($sql, $params);
        }
        if ($id_almacen) {
            $sql .= ' AND am.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        $sql .= ' ORDER BY m.nombre ASC';

        return DB::select($sql, $params);
    }

    /**
     * Verificar si la mina ya esta siendo abastecida por el almacen
     * @param int $id_almacen
     * @param int $id_mina
     */
    public static function verificar_abastecimiento_mina(int $id_almacen, int $id_mina)
    {
        return AlmacenMina::where('id_almacen', $id_almacen)
            ->where('id_mina', $id_mina)
            ->exists();
    }

    /**
     * Asignar nueva mina por abastecer
     * @param int $id_almacen
     * @param int $id_mina
     */
    public static function nueva_mina_por_abastecer(int $id_almacen, int $id_mina)
    {
        return AlmacenMina::insertGetId([
            'id_almacen' => $id_almacen,
            'id_mina' => $id_mina
        ]);
    }

    /**
     * Obtener los datos de una mina abstecida
     * @param int $id_almacen_mina
     */
    public static function get_mina_abastecida_by_id(int $id_almacen_mina)
    {
        return self::get_minas_abastecidas(id_almacen_mina: $id_almacen_mina);
    }

    /**
     * Dejar de abastecer a una mina
     * @param int $id_asignacion
     */
    public static function eliminar_abastecimiento_mina(int $id_mina_almacen)
    {
        AlmacenMina::where('id', $id_mina_almacen)->delete();
    }
}
