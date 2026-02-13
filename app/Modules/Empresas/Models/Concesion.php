<?php

namespace App\Modules\Empresas\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Concesion extends Model
{

    // obtener la lista de concesiones con conteo de empresas asignadas
    public static function get_concesiones()
    {
        $sql = '
        SELECT
            cn.id AS id_concesion,
            cn.nombre,
            cn.codigo_concesion,
            cn.codigo_reinfo,
            cn.ubigeo,
            cn.tipo_mineral,
            cn.estado,
            (SELECT COUNT(*) FROM empresa_concesion ec WHERE ec.id_concesion = cn.id AND ec.estado = :estado_activo) as empresas_asignadas
        FROM
            concesion cn
        WHERE
            cn.estado = :estado
        ORDER BY cn.id DESC
        ';

        return DB::select($sql, [
            'estado' => EstadoBase::Activo->value,
            'estado_activo' => EstadoBase::Activo->value
        ]);
    }

    // obtener las concesiones donde trabaja una empresa (a través de la tabla intermedia)
    public static function get_concesiones_by_empresa(int $id_empresa)
    {
        $sql = '
        SELECT
            cn.id AS id_concesion,
            cn.nombre,
            cn.codigo_concesion,
            cn.codigo_reinfo,
            cn.ubigeo,
            cn.tipo_mineral,
            cn.estado,
            ec.id AS id_asignacion,
            ec.fecha_inicio,
            ec.fecha_fin
        FROM
            concesion cn
        INNER JOIN empresa_concesion ec ON ec.id_concesion = cn.id
        WHERE
            ec.id_empresa = :id_empresa AND
            cn.estado = :estado
            -- AND UPPER(ec.estado) = UPPER(:estado)
        ';

        return DB::select($sql, [
            'id_empresa' => $id_empresa,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    // obtener las concesiones donde trabaja un usuario (a través de empresa asignada)
    public static function get_concesiones_by_usuario(int $id_usuario)
    {
        $sql = '
        SELECT DISTINCT
            cn.id AS id_concesion,
            cn.nombre,
            cn.codigo_concesion,
            cn.codigo_reinfo,
            cn.ubigeo,
            cn.tipo_mineral,
            cn.estado
        FROM
            concesion cn
        INNER JOIN empresa_concesion ec ON ec.id_concesion = cn.id
        INNER JOIN usuario_empresa ue ON ue.id_empresa = ec.id_empresa
        WHERE
            ue.id_usuario = :id_usuario AND
            cn.estado = :estado AND
            ec.estado = :estado
        ORDER BY cn.nombre ASC
        ';

        return DB::select($sql, [
            'id_usuario' => $id_usuario,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    // crear una concesion (Campos actualizados)
    public static function crear_concesion(string $nombre, ?string $codigo_concesion, ?string $codigo_reinfo, ?string $ubigeo, ?string $tipo_mineral)
    {
        return DB::table('concesion')->insertGetId([
            'nombre'           => $nombre,
            'codigo_concesion' => $codigo_concesion,
            'codigo_reinfo'    => $codigo_reinfo,
            'ubigeo'           => $ubigeo,
            'tipo_mineral'     => $tipo_mineral,
            'estado'           => EstadoBase::Activo->value
        ]);
    }

    // verificar que no exista una concesion con el mismo nombre (Global, ya no por empresa)
    public static function verificar_concesion_existente(string $nombre, ?int $id_excluir = null)
    {
        $query = DB::table('concesion')
            ->where('nombre', $nombre)
            ->where('estado', EstadoBase::Activo->value);

        if ($id_excluir) {
            $query->where('id', '!=', $id_excluir);
        }

        return $query->exists();
    }
    // actualizar una concesion
    public static function update_concesion(int $id, string $nombre)
    {
        return DB::table('concesion')
            ->where('id', $id)
            ->update([
                'nombre' => $nombre
            ]);
    }

    // eliminar (desactivar) una concesion
    public static function delete_concesion(int $id)
    {
        return DB::table('concesion')
            ->where('id', $id)
            ->update([
                'estado' => EstadoBase::Inactivo->value
            ]);
    }

    // obtener concesion por id
    public static function get_concesion_by_id(int $id)
    {
        $sql = '
        SELECT
            cn.id AS id_concesion,
            cn.nombre,
            cn.codigo_concesion,
            cn.codigo_reinfo,
            cn.ubigeo,
            cn.tipo_mineral,
            cn.estado
        FROM
            concesion cn
        WHERE
            cn.id = :id AND
            cn.estado = :estado
        ';

        return DB::selectOne($sql, [
            'id' => $id,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    // --- MÉTODOS PARA ASIGNACIÓN DE EMPRESAS (N:M) ---

    // Asignar una empresa a una concesion
    public static function asignar_empresa(int $id_concesion, int $id_empresa, string $fecha_inicio, ?string $fecha_fin)
    {
        return DB::table('empresa_concesion')->insertGetId([
            'id_concesion' => $id_concesion,
            'id_empresa'   => $id_empresa,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin'    => $fecha_fin,
            'estado'       => EstadoBase::Activo->value
        ]);
    }

    // Obtener empresas asignadas a una concesion
    public static function get_empresas_asignadas(int $id_concesion)
    {
        $sql = '
        SELECT
            ec.id AS id_asignacion,
            ec.id_empresa,
            e.nombre_comercial,
            e.ruc,
            e.path_logo,
            ec.fecha_inicio,
            ec.fecha_fin,
            ec.estado
        FROM
            empresa_concesion ec
        INNER JOIN empresa e ON e.id = ec.id_empresa
        WHERE
            ec.id_concesion = :id_concesion AND
            ec.estado = :estado
        ORDER BY ec.fecha_inicio DESC
        ';

        return DB::select($sql, [
            'id_concesion' => $id_concesion,
            'estado'       => EstadoBase::Activo->value
        ]);
    }

    // Verificar si una empresa ya tiene una asignación activa en el rango de fechas (simple check de solapamiento)
    public static function verificar_asignacion_activa(int $id_concesion, int $id_empresa)
    {
        return DB::table('empresa_concesion')
            ->where('id_concesion', $id_concesion)
            ->where('id_empresa', $id_empresa)
            ->where('estado', EstadoBase::Activo->value)
            ->exists();
    }

    // Desasignar (eliminación lógica)
    public static function desasignar_empresa(int $id_asignacion)
    {
        return DB::table('empresa_concesion')
            ->where('id', $id_asignacion)
            ->update(['estado' => EstadoBase::Inactivo->value]);
    }
}
