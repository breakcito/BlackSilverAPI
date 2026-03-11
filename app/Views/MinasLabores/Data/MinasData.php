<?php

namespace App\Views\MinasLabores\Data;

use App\Models\Mina;
use App\Shared\Enums\EstadoBase;
use Illuminate\Support\Facades\DB;

class MinasData
{
    /**
     * Concesiones accesibles por el usuario
     */
    public static function get_concesiones(int $id_usuario)
    {
        $sql = '
        SELECT DISTINCT
            cn.id AS id_concesion,
            cn.nombre
        FROM
            concesion cn
        INNER JOIN contrato_concesion ctr ON
            ctr.id_concesion = cn.id
        INNER JOIN empresa emp ON
            emp.id = ctr.id_empresa
        INNER JOIN usuario_empresa usu ON
            usu.id_empresa = emp.id
        WHERE
            ctr.estado = "Activo" AND
            usu.id_usuario = :id_usuario
        ';

        return DB::select($sql, [
            'id_usuario' => $id_usuario,
        ]);
    }

    /**
     * Resumen de minas de una concesión
     */
    public static function get_resumen_minas(?int $id_concesion = null, ?int $id_mina = null)
    {
        $sql = '
        SELECT DISTINCT
            mn.id AS id_mina,
            mn.id_concesion,
            mn.nombre,
            mn.descripcion,
            CONCAT(em.nombre, " ", em.apellido) as responsable,
            em.dni as dni_responsable,
            em.path_foto as path_foto_responsable,
            res.fecha_inicio as fecha_inicio_responsabilidad,
            (
                SELECT
                    COUNT(*)
                FROM labor lb
                WHERE
                    lb.id_mina = mn.id AND
                    lb.estado = "Activo"
            ) as cantidad_labores,
            (
                SELECT DISTINCT
                    COUNT(*)
                FROM empresa_mina emi
                WHERE
                    emi.id_mina = mn.id
            ) as cantidad_empresas_ejecutoras,
            mn.estado
        FROM
            mina mn
        LEFT JOIN responsable_mina res ON
            res.id_mina = mn.id AND
            res.estado = "Activo" AND
            res.fecha_fin IS NULL
        LEFT JOIN empleado em on em.id = res.id_empleado
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
