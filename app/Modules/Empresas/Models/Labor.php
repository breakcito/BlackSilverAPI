<?php

namespace App\Modules\Empresas\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Labor extends Model
{
    // obtener labores, opcionalmente filtrar por mina
    public static function get_labores(?int $id_mina = null)
    {
        $sql = '
        SELECT
            l.id as id_labor,
            l.id_empresa,
            l.id_mina,
            l.id_tipo_labor,
            m.nombre as mina,
            e.nombre_comercial as empresa,
            tl.nombre as tipo_labor_nombre,
            tl.is_produccion,
            l.codigo_correlativo,
            l.nombre,
            l.descripcion,
            l.tipo_sostenimiento,
            l.estado,
            (
                SELECT CONCAT(emp.nombre, \' \', emp.apellido)
                FROM responsable_labor rl
                INNER JOIN usuario_empresa ue ON ue.id = rl.id_usuario
                INNER JOIN usuario u ON u.id = ue.id_usuario
                INNER JOIN empleado emp ON emp.id = u.id_empleado
                WHERE rl.id_labor = l.id AND rl.estado = :estado_activo
                LIMIT 1
            ) as responsable_actual
        FROM
            labor l
        INNER JOIN mina m ON m.id = l.id_mina
        INNER JOIN empresa e ON e.id = l.id_empresa
        INNER JOIN tipo_labor tl ON tl.id = l.id_tipo_labor
        ';
        
        $params = ['estado_activo' => EstadoBase::Activo->value];

        if ($id_mina) {
            $sql .= ' WHERE l.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        $sql .= ' ORDER BY l.codigo_correlativo ASC';

        return DB::select($sql, $params);
    }

    public static function get_labor_by_id(int $id)
    {
        $sql = '
        SELECT
            l.id as id_labor,
            l.id_empresa,
            l.id_mina,
            l.id_tipo_labor,
            m.nombre as mina,
            e.nombre_comercial as empresa,
            tl.nombre as tipo_labor_nombre,
            tl.is_produccion,
            l.codigo_correlativo,
            l.nombre,
            l.descripcion,
            l.tipo_sostenimiento,
            l.estado
        FROM
            labor l
        INNER JOIN mina m ON m.id = l.id_mina
        INNER JOIN empresa e ON e.id = l.id_empresa
        INNER JOIN tipo_labor tl ON tl.id = l.id_tipo_labor
        WHERE
            l.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }

    public static function verificar_labor_existente(int $id_mina, string $nombre, ?int $id_excluir = null)
    {
        $query = DB::table('labor')
            ->where('id_mina', $id_mina)
            ->where('nombre', $nombre);

        if ($id_excluir) {
            $query->where('id', '!=', $id_excluir);
        }

        return $query->exists();
    }

    public static function crear_labor(int $id_empresa, int $id_mina, int $id_tipo_labor, string $codigo_correlativo, string $nombre, ?string $descripcion, string $tipo_sostenimiento)
    {
        return DB::table('labor')->insertGetId([
            'id_empresa'         => $id_empresa,
            'id_mina'            => $id_mina,
            'id_tipo_labor'      => $id_tipo_labor,
            'codigo_correlativo' => $codigo_correlativo,
            'nombre'             => $nombre,
            'descripcion'        => $descripcion,
            'tipo_sostenimiento' => $tipo_sostenimiento,
            'estado'             => EstadoBase::Activo->value
        ]);
    }

    public static function update_labor(int $id, int $id_empresa, int $id_mina, int $id_tipo_labor, string $codigo_correlativo, string $nombre, ?string $descripcion, string $tipo_sostenimiento)
    {
        return DB::table('labor')
            ->where('id', $id)
            ->update([
                'id_empresa'         => $id_empresa,
                'id_mina'            => $id_mina,
                'id_tipo_labor'      => $id_tipo_labor,
                'codigo_correlativo' => $codigo_correlativo,
                'nombre'             => $nombre,
                'descripcion'        => $descripcion,
                'tipo_sostenimiento' => $tipo_sostenimiento
            ]);
    }

    public static function delete_labor(int $id)
    {
        return DB::table('labor')
            ->where('id', $id)
            ->update(['estado' => EstadoBase::Inactivo->value]); // Soft delete generalmente
    }
    // --- MÉTODOS PARA RESPONSABLE DE LABOR (responsable_labor) ---

    public static function asignar_responsable(int $id_labor, int $id_usuario, string $fecha_inicio, ?string $fecha_fin)
    {
        return DB::table('responsable_labor')->insertGetId([
            'id_labor'     => $id_labor,
            'id_usuario'   => $id_usuario, // id_usuario tabla usuario, no usuario_empresa según tu esquema nuevo
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin'    => $fecha_fin,
            'estado'       => EstadoBase::Activo->value
        ]);
    }

    public static function get_responsable_activo(int $id_labor)
    {
        $sql = '
        SELECT
            rl.id as id_asignacion,
            rl.id_usuario,
            emp.nombre as nombres,
            emp.apellido as apellidos,
            rl.fecha_inicio
        FROM
            responsable_labor rl
        INNER JOIN usuario u ON u.id = rl.id_usuario
        INNER JOIN empleado emp ON emp.id = u.id_empleado
        WHERE
            rl.id_labor = :id_labor AND
            rl.estado = :estado
        ';

        return DB::selectOne($sql, [
            'id_labor' => $id_labor,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    public static function cerrar_responsable_activo(int $id_labor, string $fecha_cierre)
    {
        return DB::table('responsable_labor')
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
            rl.id as id_asignacion,
            rl.id_usuario,
            emp.nombre as nombres,
            emp.apellido as apellidos,
            rl.fecha_inicio,
            rl.fecha_fin,
            rl.estado
        FROM
            responsable_labor rl
        INNER JOIN usuario u ON u.id = rl.id_usuario
        INNER JOIN empleado emp ON emp.id = u.id_empleado
        WHERE
            rl.id_labor = :id_labor
        ORDER BY rl.fecha_inicio DESC
        ';

        return DB::select($sql, ['id_labor' => $id_labor]);
    }

    /**
     * Verificar si el usuario pertenece a una empresa con contrato vigente en la concesión dada.
     */
    public static function check_usuario_autorizado(int $id_usuario, int $id_concesion)
    {
        return DB::table('usuario_empresa as ue')
            ->join('contrato_concesion as cc', 'cc.id_empresa', '=', 'ue.id_empresa')
            ->where('ue.id_usuario', $id_usuario)
            ->where('cc.id_concesion', $id_concesion)
            ->exists();
    }

    /**
     * Verificar si el usuario pertenece a la empresa encargada de la labor.
     */
    public static function check_usuario_pertenece_empresa(int $id_usuario, int $id_empresa)
    {
        return DB::table('usuario_empresa')
            ->where('id_usuario', $id_usuario)
            ->where('id_empresa', $id_empresa)
            ->exists();
    }
}
