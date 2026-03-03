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

    // Listar minas 
    public static function get_minas(?int $id_mina = null, ?int $id_concesion = null)
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
                INNER JOIN empleado emp ON emp.id = rm.id_empleado
                WHERE rm.id_mina = m.id AND rm.estado = "Activo"
                LIMIT 1
            ) AS responsable_actual
        FROM
            mina m
        INNER JOIN concesion c ON c.id = m.id_concesion
        WHERE
            1 = 1
        ';

        $params = [];
        if ($id_concesion) {
            $sql .= ' AND m.id_concesion = :id_concesion';
            $params['id_concesion'] = $id_concesion;
        }
        if ($id_mina) {
            $sql .= ' AND m.id = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY m.nombre ASC';

        return DB::select($sql, $params);
    }

    public static function check_usuario_autorizado_mina(int $id_usuario, int $id_mina)
    {
        $mina = self::where('id', $id_mina)->first();
        if (! $mina) {
            return false;
        }

        $sql = '
        SELECT EXISTS (
            SELECT 1
            FROM usuario_empresa ue
            INNER JOIN empresa_mina em ON em.id_empresa = ue.id_empresa
            INNER JOIN contrato_concesion cc ON cc.id_empresa = ue.id_empresa
            WHERE ue.id_usuario = :id_usuario
              AND em.id_mina = :id_mina
              AND cc.id_concesion = :id_concesion
              AND cc.estado = :estado_activo
        ) AS is_authorized
        ';

        $result = DB::selectOne($sql, [
            'id_usuario' => $id_usuario,
            'id_mina' => $id_mina,
            'id_concesion' => $mina->id_concesion,
            'estado_activo' => EstadoBase::Activo->value,
        ]);

        return (bool) $result->is_authorized;
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
