<?php

namespace App\Modules\MinasLabores\Data;

use App\Models\Mina;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class MinasData
{
    /**
     * Resumen de minas de una concesión
     */
    public static function get_resumen_minas(?int $id_concesion = null, ?int $id_mina = null)
    {
        $sql = '
        SELECT 
            mn.id AS id_mina,
            mn.id_concesion,
            cn.nombre AS concesion,
            mn.nombre,
            mn.descripcion,
            -- Lista de responsables concatenada 
            (
                SELECT GROUP_CONCAT(CONCAT(con.nombre, " ", con.apellido) ORDER BY res.id DESC SEPARATOR ", ")
                FROM responsable_mina res
                INNER JOIN empleado con ON con.id = res.id_empleado
                WHERE 
                    res.id_mina = mn.id AND 
                    res.estado = "Activo" AND 
                    res.fecha_fin IS NULL
            ) AS responsables,
            -- Conteo de labores activas
            (
                SELECT COUNT(*)
                FROM labor lb
                WHERE 
                    lb.id_mina = mn.id AND 
                    lb.estado = "Activo"
            ) AS cantidad_labores,
            -- Conteo de empresas ejecutoras únicas
            (
                SELECT COUNT(DISTINCT emi.id_empresa)
                FROM empresa_mina emi
                WHERE emi.id_mina = mn.id
            ) AS cantidad_empresas_ejecutoras,
            -- Lista de almacenes concatenada
            (
                SELECT GROUP_CONCAT(a.nombre SEPARATOR ", ")
                FROM almacen_mina am
                JOIN almacen a ON a.id = am.id_almacen
                WHERE am.id_mina = mn.id
            ) AS almacenes_suministradores,
            mn.estado
        FROM 
            mina mn
        INNER JOIN concesion cn ON cn.id = mn.id_concesion
        WHERE 
            1 = 1
        ';

        $params = [];

        if ($id_mina !== null) {
            $sql .= ' AND mn.id = :id_mina';
            $params['id_mina'] = $id_mina;

            return DB::selectOne($sql, $params);
        }

        if ($id_concesion !== null) {
            $sql .= ' AND mn.id_concesion = :id_concesion';
            $params['id_concesion'] = $id_concesion;
        }

        $sql .= ' ORDER BY mn.nombre ASC';

        return DB::select($sql, $params);
    }

    public static function get_mina_by_id(int $id_mina)
    {
        return self::get_resumen_minas(id_mina: $id_mina);
    }


    public static function existe_nombre(int $id_concesion, string $nombre): bool
    {
        return Mina::where('id_concesion', $id_concesion)
            ->where('nombre', $nombre)
            ->exists();
    }

    public static function crear_mina(int $id_concesion, string $nombre, ?string $descripcion)
    {
        return Mina::insertGetId([
            'id_concesion' => $id_concesion,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'estado' => EstadoBase::Activo->value,
        ]);
    }
}
