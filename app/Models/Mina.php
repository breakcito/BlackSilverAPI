<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Mina extends Model
{
    protected $table = 'mina';

    public $timestamps = false;

    protected $fillable = [
        'id_concesion',
        'nombre',
        'descripcion',
        'estado',
    ];

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
            (SELECT COUNT(*) FROM empresa_mina em WHERE em.id_mina = m.id) AS empresas_count,
            (SELECT COUNT(*) FROM labor l WHERE l.id_mina = m.id AND l.estado != \'Inactivo\') AS labores_count,
            (
                SELECT CONCAT(emp.nombre, \' \', emp.apellido)
                FROM responsable_mina rm
                INNER JOIN usuario u ON u.id = rm.id_usuario
                INNER JOIN empleado emp ON emp.id = u.id_empleado
                WHERE rm.id_mina = m.id AND rm.estado = :estado_activo
                LIMIT 1
            ) AS responsable_actual
        FROM
            mina m
        INNER JOIN concesion c ON c.id = m.id_concesion
        WHERE
            m.estado = :estado
        ';

        $params = [
            'estado' => EstadoBase::Activo->value,
            'estado_activo' => EstadoBase::Activo->value,
        ];

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
            m.estado,
            (SELECT COUNT(*) FROM empresa_mina em WHERE em.id_mina = m.id) AS empresas_count,
            (SELECT COUNT(*) FROM labor l WHERE l.id_mina = m.id AND l.estado != "Inactivo") AS labores_count,
            (
                SELECT CONCAT(emp.nombre, " ", emp.apellido)
                FROM responsable_mina rm
                INNER JOIN usuario u ON u.id = rm.id_usuario
                INNER JOIN empleado emp ON emp.id = u.id_empleado
                WHERE rm.id_mina = m.id AND rm.estado = :estado_activo
                LIMIT 1
            ) AS responsable_actual
        FROM
            mina m
        INNER JOIN concesion c ON c.id = m.id_concesion
        WHERE
            m.id = :id
        ';

        return DB::selectOne($sql, [
            'id' => $id,
            'estado_activo' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Crear mina.
     */
    public static function crear_mina(int $id_concesion, string $nombre, ?string $descripcion)
    {
        return self::insertGetId([
            'id_concesion' => $id_concesion,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Actualizar mina.
     */
    public static function update_mina(int $id, int $id_concesion, string $nombre, ?string $descripcion)
    {
        return self::where('id', $id)
            ->update([
                'id_concesion' => $id_concesion,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
            ]);
    }

    /**
     * Eliminar mina.
     */
    public static function delete_mina(int $id)
    {
        return self::where('id', $id)
            ->update(['estado' => EstadoBase::Inactivo->value]);
    }

    // --- RELACIÓN EMPRESA_MINA ---

    /**
     * Verificar si la empresa tiene contrato vigente en la concesión de la mina target.
     */
    public static function check_contrato_vigente(int $id_concesion, int $id_empresa)
    {
        return \Illuminate\Support\Facades\DB::table('contrato_concesion')
            ->where('id_concesion', $id_concesion)
            ->where('id_empresa', $id_empresa)
            ->where('estado', EstadoBase::Activo->value)
            ->exists();
    }

    // --- RESPONSABLE DE MINA (responsable_mina) ---

    public static function check_usuario_autorizado_mina(int $id_usuario, int $id_mina)
    {
        $mina = self::where('id', $id_mina)->first();
        if (! $mina) {
            return false;
        }

        return DB::table('usuario_empresa as ue')
            ->join('empresa_mina as em', 'em.id_empresa', '=', 'ue.id_empresa')
            ->join('contrato_concesion as cc', 'cc.id_empresa', '=', 'ue.id_empresa')
            ->where('ue.id_usuario', $id_usuario)
            ->where('em.id_mina', $id_mina)
            ->where('cc.id_concesion', $mina->id_concesion)
            ->where('cc.estado', EstadoBase::Activo->value)
            ->exists();
    }

    /**
     * Obtener lista de usuarios que pueden ser responsables de esta mina.
     * (Pertenece a empresa vinculada a la mina Y con contrato vigente en la concesión).
     */
    public static function get_usuarios_autorizados(int $id_mina)
    {
        $mina = self::where('id', $id_mina)->first();
        if (! $mina) {
            return [];
        }

        $sql = '
        SELECT
            ue.id AS id_usuario_empresa,
            u.id AS id_usuario,
            emp.nombre,
            emp.apellido,
            e.nombre_comercial AS empresa
        FROM
            usuario u
        INNER JOIN empleado emp ON emp.id = u.id_empleado
        INNER JOIN usuario_empresa ue ON ue.id_usuario = u.id
        INNER JOIN empresa e ON e.id = ue.id_empresa
        INNER JOIN empresa_mina em ON em.id_empresa = e.id
        INNER JOIN contrato_concesion cc ON cc.id_empresa = e.id
        WHERE
            em.id_mina = :id_mina
            AND cc.id_concesion = :id_concesion
            AND cc.estado = :estado_activo
            AND emp.estado = :estado_activo
        ORDER BY emp.nombre ASC, emp.apellido ASC
        ';

        return DB::select($sql, [
            'id_mina' => $id_mina,
            'id_concesion' => $mina->id_concesion,
            'estado_activo' => EstadoBase::Activo->value,
        ]);
    }
}
