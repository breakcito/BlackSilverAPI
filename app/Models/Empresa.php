<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo para la tabla empresa.
 */
class Empresa extends Model
{
    protected $table = 'empresa';
    public $timestamps = false;
    protected $fillable = [
        'ruc',
        'razon_social',
        'nombre_comercial',
        'abreviatura',
        'path_logo',
        'estado',
    ];

    /**
     * Obtener todas las empresas.
     */
    public static function get_empresas()
    {
        $sql = '
        SELECT
            e.id as id_empresa,
            e.ruc,
            e.razon_social,
            e.nombre_comercial,
            e.abreviatura,
            e.path_logo
        FROM
            empresa e
        ORDER BY e.nombre_comercial
        ';

        return DB::select($sql);
    }

    /**
     * Buscar empresas asociadas a un usuario
     */
    public static function get_empresas_by_usuario(int $id_usuario)
    {
        $sql = '
        SELECT
            emp.id AS id_empresa,
            emp.ruc,
            emp.razon_social,
            emp.nombre_comercial,
            emp.abreviatura,
            emp.path_logo
        FROM
            empresa emp
        INNER JOIN usuario_empresa uem ON
            uem.id_empresa = emp.id
        WHERE
            uem.id_usuario = :id_usuario
        ';

        return DB::select($sql, [
            'id_usuario' => $id_usuario,
        ]);
    }

    /**
     * Obtener una empresa por ID
     */
    public static function get_empresa_by_id(int $id)
    {
        $sql = '
        SELECT
            e.id as id_empresa,
            e.ruc,
            e.razon_social,
            e.nombre_comercial,
            e.abreviatura,
            e.path_logo
        FROM
            empresa e
        WHERE
            e.id = :id
        ';

        return DB::selectOne($sql, [
            'id' => $id,
        ]);
    }

    /**
     * Verificar si existe una empresa por RUC (para evitar duplicados al crear)
     */
    public static function verificar_empresa_existente(string $ruc, ?int $id_excluir = null)
    {
        $query = DB::table('empresa')
            ->where('ruc', $ruc);

        if ($id_excluir) {
            $query->where('id', '!=', $id_excluir);
        }

        return $query->exists();
    }

    /**
     * Crear una nueva empresa
     */
    public static function crear_empresa(string $ruc, string $razon_social, string $nombre_comercial, string $abreviatura, string $path_logo)
    {
        return DB::table('empresa')->insertGetId([
            'ruc' => $ruc,
            'razon_social' => $razon_social,
            'nombre_comercial' => $nombre_comercial,
            'abreviatura' => $abreviatura,
            'path_logo' => $path_logo,
        ]);
    }

    /**
     * Obtener usuarios por empresa
     */
    public static function get_usuarios_por_empresa(int $id_empresa)
    {
        $sql = '
        SELECT
            ue.id as id_usuario_empresa,
            e.nombre as nombres,
            e.apellido as apellidos,
            c.nombre as cargo,
            u.username
        FROM
            usuario_empresa ue
        INNER JOIN usuario u ON u.id = ue.id_usuario
        INNER JOIN empleado e ON e.id = u.id_empleado
        LEFT JOIN cargo c ON c.id = e.id_cargo
        WHERE
            ue.id_empresa = :id_empresa
        ORDER BY e.apellido ASC
        ';

        return DB::select($sql, [
            'id_empresa' => $id_empresa,
        ]);
    }

    /**
     * Obtener prefijo de una empresa
     */
    public static function get_prefijo_empresa(int $id_empresa)
    {
        $sql = '
        SELECT
            e.prefijo
        FROM
            empresa e
        WHERE
            e.id = :id_empresa
        ';

        return DB::selectOne($sql, [
            'id_empresa' => $id_empresa,
        ]);
    }
}
