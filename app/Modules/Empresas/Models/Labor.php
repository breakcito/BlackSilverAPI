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
            l.veta,
            l.ancho,
            l.alto,
            l.nivel,
            l.fecha_fin,
            l.created_at,
            l.estado
        FROM
            labor l
        INNER JOIN mina m ON m.id = l.id_mina
        INNER JOIN empresa e ON e.id = l.id_empresa
        INNER JOIN tipo_labor tl ON tl.id = l.id_tipo_labor
        ';
        
        $params = [];

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
            l.veta,
            l.ancho,
            l.alto,
            l.nivel,
            l.fecha_fin,
            l.created_at,
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

    public static function crear_labor(
        int $id_empresa, 
        int $id_mina, 
        int $id_tipo_labor, 
        string $codigo_correlativo, 
        string $nombre, 
        ?string $descripcion, 
        string $tipo_sostenimiento,
        ?string $veta = null,
        ?float $ancho = null,
        ?float $alto = null,
        ?string $nivel = null
    ) {
        return DB::table('labor')->insertGetId([
            'id_empresa'         => $id_empresa,
            'id_mina'            => $id_mina,
            'id_tipo_labor'      => $id_tipo_labor,
            'codigo_correlativo' => $codigo_correlativo,
            'nombre'             => $nombre,
            'descripcion'        => $descripcion,
            'tipo_sostenimiento' => $tipo_sostenimiento,
            'veta'               => $veta,
            'ancho'              => $ancho,
            'alto'               => $alto,
            'nivel'              => $nivel,
            'created_at'         => now(),
            'estado'             => EstadoBase::Activo->value
        ]);
    }

    public static function update_labor(
        int $id, 
        int $id_empresa, 
        int $id_mina, 
        int $id_tipo_labor, 
        string $codigo_correlativo, 
        string $nombre, 
        ?string $descripcion, 
        string $tipo_sostenimiento,
        ?string $veta = null,
        ?float $ancho = null,
        ?float $alto = null,
        ?string $nivel = null,
        ?string $fecha_fin = null,
        ?string $estado = null
    ) {
        $update = [
            'id_empresa'         => $id_empresa,
            'id_mina'            => $id_mina,
            'id_tipo_labor'      => $id_tipo_labor,
            'codigo_correlativo' => $codigo_correlativo,
            'nombre'             => $nombre,
            'descripcion'        => $descripcion,
            'tipo_sostenimiento' => $tipo_sostenimiento,
            'veta'               => $veta,
            'ancho'              => $ancho,
            'alto'               => $alto,
            'nivel'              => $nivel,
            'fecha_fin'          => $fecha_fin
        ];

        if ($estado) {
            $update['estado'] = $estado;
        }

        return DB::table('labor')
            ->where('id', $id)
            ->update($update);
    }

    public static function delete_labor(int $id)
    {
        return DB::table('labor')
            ->where('id', $id)
            ->update(['estado' => EstadoBase::Inactivo->value, 'fecha_fin' => now()]);
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
