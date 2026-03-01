<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Categoria extends Model
{
    protected $table = 'categoria';
    public $timestamps = false;
    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo_requerimiento',
        'clasificacion_bien',
        'estado',
    ];

    public static function get_categorias(?string $tipo_requerimiento = null)
    {
        $sql = '
        SELECT
            c.id as id_categoria,
            c.nombre,
            c.descripcion,
            c.tipo_requerimiento,
            c.clasificacion_bien,
            c.estado
        FROM
            categoria c
        WHERE
            c.estado = :estado
        ';

        $params = ['estado' => EstadoBase::Activo->value];

        if ($tipo_requerimiento) {
            $sql .= ' AND c.tipo_requerimiento = :tipo_requerimiento';
            $params['tipo_requerimiento'] = $tipo_requerimiento;
        }

        return DB::select($sql, $params);
    }

    public static function get_categoria_by_id(int $id)
    {
        $sql = '
        SELECT
            c.id as id_categoria,
            c.nombre,
            c.descripcion,
            c.tipo_requerimiento,
            c.clasificacion_bien,
            c.estado
        FROM
            categoria c
        WHERE
            c.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }

    public static function crear_categoria(string $nombre, ?string $descripcion, string $tipo_requerimiento, ?string $clasificacion_bien)
    {
        return DB::table('categoria')->insertGetId([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo_requerimiento' => $tipo_requerimiento,
            'clasificacion_bien' => $clasificacion_bien,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    public static function update_categoria(int $id, string $nombre, ?string $descripcion, string $tipo_requerimiento, ?string $clasificacion_bien)
    {
        return DB::table('categoria')
            ->where('id', $id)
            ->update([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'tipo_requerimiento' => $tipo_requerimiento,
                'clasificacion_bien' => $clasificacion_bien,
            ]);
    }

    public static function delete_categoria(int $id)
    {
        return DB::table('categoria')
            ->where('id', $id)
            ->update([
                'estado' => EstadoBase::Inactivo->value,
            ]);
    }

    public static function verificar_categoria_existente(string $nombre, ?int $id_excluir = null)
    {
        $query = DB::table('categoria')
            ->whereRaw('LOWER(nombre) = LOWER(?)', [$nombre])
            ->where('estado', EstadoBase::Activo->value);

        if ($id_excluir) {
            $query->where('id', '!=', $id_excluir);
        }

        return $query->exists();
    }
}
