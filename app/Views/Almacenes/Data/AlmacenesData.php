<?php

namespace App\Views\Almacenes\Data;

use App\Models\Almacen;
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
}
