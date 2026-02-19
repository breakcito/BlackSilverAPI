<?php

namespace App\Modules\Empresas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Shared\Enums\EstadoBase;

class Mina extends Model
{
    protected $table = 'mina';

    /**
     * Listar minas (Opcional filtrar por concesión)
     */
    public static function get_minas(?int $id_concesion = null)
    {
        $sql = '
        SELECT
            m.id AS id_mina,
            m.id_concesion,
            c.nombre AS concesion,
            m.nombre,
            m.descripcion,
            m.estado,
            (SELECT COUNT(*) FROM empresa_mina em WHERE em.id_mina = m.id) AS empresas_count
        FROM
            mina m
        INNER JOIN concesion c ON c.id = m.id_concesion
        WHERE
            m.estado = :estado
        ';

        $params = ['estado' => EstadoBase::Activo->value];

        if ($id_concesion) {
            $sql .= ' AND m.id_concesion = :id_concesion';
            $params['id_concesion'] = $id_concesion;
        }

        $sql .= ' ORDER BY m.nombre ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener mina por ID.
     */
    public static function get_mina_by_id(int $id)
    {
        $sql = '
        SELECT
            m.id AS id_mina,
            m.id_concesion,
            c.nombre AS concesion,
            m.nombre,
            m.descripcion,
            m.estado
        FROM
            mina m
        INNER JOIN concesion c ON c.id = m.id_concesion
        WHERE
            m.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }

    /**
     * Crear mina.
     */
    public static function crear_mina(int $id_concesion, string $nombre, ?string $descripcion)
    {
        return DB::table('mina')->insertGetId([
            'id_concesion' => $id_concesion,
            'nombre'       => $nombre,
            'descripcion'  => $descripcion,
            'estado'       => EstadoBase::Activo->value
        ]);
    }

    /**
     * Actualizar mina.
     */
    public static function update_mina(int $id, int $id_concesion, string $nombre, ?string $descripcion)
    {
        return DB::table('mina')
            ->where('id', $id)
            ->update([
                'id_concesion' => $id_concesion,
                'nombre'       => $nombre,
                'descripcion'  => $descripcion
            ]);
    }

    /**
     * Eliminar mina.
     */
    public static function delete_mina(int $id)
    {
        return DB::table('mina')
            ->where('id', $id)
            ->update(['estado' => EstadoBase::Inactivo->value]);
    }

    // --- RELACIÓN EMPRESA_MINA ---

    /**
     * Asignar empresa a mina.
     */
    public static function asignar_empresa(int $id_mina, int $id_empresa)
    {
        return DB::table('empresa_mina')->insertGetId([
            'id_mina'    => $id_mina,
            'id_empresa' => $id_empresa
        ]);
    }

    /**
     * Desasignar empresa de mina (Eliminación física, es tabla de relación simple).
     */
    public static function desasignar_empresa(int $id_asignacion)
    {
        return DB::table('empresa_mina')->where('id', $id_asignacion)->delete();
    }

    /**
     * Listar empresas asignadas a una mina.
     */
    public static function get_empresas_asignadas(int $id_mina)
    {
        $sql = '
        SELECT
            em.id AS id_asignacion,
            em.id_empresa,
            e.nombre_comercial,
            e.ruc,
            e.path_logo
        FROM
            empresa_mina em
        INNER JOIN empresa e ON e.id = em.id_empresa
        WHERE
            em.id_mina = :id_mina
        ORDER BY e.nombre_comercial ASC
        ';

        return DB::select($sql, ['id_mina' => $id_mina]);
    }

    /**
     * Verificar si empresa ya está en mina.
     */
    public static function verificar_empresa_asignada(int $id_mina, int $id_empresa)
    {
        return DB::table('empresa_mina')
            ->where('id_mina', $id_mina)
            ->where('id_empresa', $id_empresa)
            ->exists();
    }

    /**
     * Verificar si la empresa tiene contrato vigente en la concesión de la mina target.
     */
    public static function check_contrato_vigente(int $id_concesion, int $id_empresa)
    {
        return DB::table('contrato_concesion')
            ->where('id_concesion', $id_concesion)
            ->where('id_empresa', $id_empresa)
            ->where('estado', EstadoBase::Activo->value)
            ->exists();
    }
}
