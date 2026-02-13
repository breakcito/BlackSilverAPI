<?php

namespace App\Modules\Empresas\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Labor extends Model
{
    // obtener labores, opcionalmente filtrar por empresa_concesion (asignacion)
    public static function get_labores(?int $id_empresa_concesion = null)
    {
        $sql = '
        SELECT
            l.id as id_labor,
            l.id_empresa_concesion,
            cn.nombre as concesion,
            e.nombre_comercial as empresa,
            l.nombre,
            l.descripcion,
            l.tipo_labor,
            l.tipo_sostenimiento
        FROM
            labor l
        INNER JOIN empresa_concesion ec ON ec.id = l.id_empresa_concesion
        INNER JOIN concesion cn ON cn.id = ec.id_concesion
        INNER JOIN empresa e ON e.id = ec.id_empresa
        ';
        
        $params = [];

        if ($id_empresa_concesion) {
            $sql .= ' WHERE l.id_empresa_concesion = :id_empresa_concesion';
            $params['id_empresa_concesion'] = $id_empresa_concesion;
        }

        return DB::select($sql, $params);
    }

    public static function get_labor_by_id(int $id)
    {
        $sql = '
        SELECT
            l.id as id_labor,
            l.id_empresa_concesion,
            cn.nombre as concesion,
            e.nombre_comercial as empresa,
            l.nombre,
            l.descripcion,
            l.tipo_labor,
            l.tipo_sostenimiento
        FROM
            labor l
        INNER JOIN empresa_concesion ec ON ec.id = l.id_empresa_concesion
        INNER JOIN concesion cn ON cn.id = ec.id_concesion
        INNER JOIN empresa e ON e.id = ec.id_empresa
        WHERE
            l.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }

    public static function verificar_labor_existente(int $id_empresa_concesion, string $nombre, ?int $id_excluir = null)
    {
        $query = DB::table('labor')
            ->where('id_empresa_concesion', $id_empresa_concesion)
            ->where('nombre', $nombre);

        if ($id_excluir) {
            $query->where('id', '!=', $id_excluir);
        }

        return $query->exists();
    }

    public static function crear_labor(int $id_empresa_concesion, string $nombre, ?string $descripcion, string $tipo_labor, string $tipo_sostenimiento)
    {
        return DB::table('labor')->insertGetId([
            'id_empresa_concesion' => $id_empresa_concesion,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo_labor' => $tipo_labor,
            'tipo_sostenimiento' => $tipo_sostenimiento
        ]);
    }

    public static function update_labor(int $id, int $id_empresa_concesion, string $nombre, ?string $descripcion, string $tipo_labor, string $tipo_sostenimiento)
    {
        return DB::table('labor')
            ->where('id', $id)
            ->update([
                'id_empresa_concesion' => $id_empresa_concesion,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'tipo_labor' => $tipo_labor,
                'tipo_sostenimiento' => $tipo_sostenimiento
            ]);
    }

    public static function delete_labor(int $id)
    {
        return DB::table('labor')
            ->where('id', $id)
            ->delete();
    }
    // --- MÉTODOS PARA RESPONSABLE DE LABOR (labor_usuario) ---

    public static function asignar_responsable(int $id_labor, int $id_usuario_empresa, string $fecha_inicio, ?string $fecha_fin, ?string $observacion)
    {
        return DB::table('labor_usuario')->insertGetId([
            'id_labor' => $id_labor,
            'id_usuario_empresa' => $id_usuario_empresa,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            // 'observacion' => $observacion, // Eliminado porque no existe en tabla
            'estado' => EstadoBase::Activo->value
        ]);
    }

    public static function get_responsable_activo(int $id_labor)
    {
        $sql = '
        SELECT
            lu.id as id_asignacion,
            lu.id_usuario_empresa,
            u.nombres,
            u.apellidos,
            lu.fecha_inicio
        FROM
            labor_usuario lu
        INNER JOIN usuario_empresa ue ON ue.id = lu.id_usuario_empresa
        INNER JOIN usuario u ON u.id = ue.id_usuario
        WHERE
            lu.id_labor = :id_labor AND
            lu.estado = :estado
        ';

        return DB::selectOne($sql, [
            'id_labor' => $id_labor,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    public static function cerrar_responsable_activo(int $id_labor, string $fecha_cierre)
    {
        return DB::table('labor_usuario')
            ->where('id_labor', $id_labor)
            ->where('estado', EstadoBase::Activo->value)
            ->update([
                'fecha_fin' => $fecha_cierre,
                'estado' => EstadoBase::Inactivo->value
            ]);
    }

    // Obtener historial de responsables
    public static function get_responsables_historial(int $id_labor)
    {
        $sql = '
        SELECT
            lu.id as id_asignacion,
            lu.id_usuario_empresa,
            u.nombres,
            u.apellidos,
            lu.fecha_inicio,
            lu.fecha_fin,
            lu.estado
        FROM
            labor_usuario lu
        INNER JOIN usuario_empresa ue ON ue.id = lu.id_usuario_empresa
        INNER JOIN usuario u ON u.id = ue.id_usuario
        WHERE
            lu.id_labor = :id_labor
        ORDER BY lu.fecha_inicio DESC
        ';

        return DB::select($sql, ['id_labor' => $id_labor]);
    }
}
