<?php

namespace App\Views\Categorias\Data;

use App\Models\Categoria;
use App\Shared\Enums\EstadoBase;
use Illuminate\Support\Facades\DB;

class CategoriasData
{
    /**
     * Listar o obtener una categoría
     */
    public static function get_categorias(?int $id_categoria = null)
    {
        $sql = '
        SELECT
            c.id AS id_categoria,
            c.nombre,
            c.descripcion,
            c.tipo_requerimiento,
            c.clasificacion_bien,
            c.estado
        FROM
            categoria c
        WHERE
            1 = 1
        ';

        $params = [];
        if ($id_categoria !== null) {
            $sql .= ' AND c.id = :id_categoria';
            $params['id_categoria'] = $id_categoria;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' AND c.estado = :estado ORDER BY c.nombre ASC';
        $params['estado'] = EstadoBase::Activo->value;

        return DB::select($sql, $params);
    }

    /**
     * Obtener una categoría por su ID
     */
    public static function get_categoria_by_id(int $id_categoria)
    {
        return self::get_categorias(id_categoria: $id_categoria);
    }

    /**
     * Crear una nueva categoría
     */
    public static function crear_categoria(array $data)
    {
        return Categoria::insertGetId([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'tipo_requerimiento' => $data['tipo_requerimiento'],
            'clasificacion_bien' => $data['clasificacion_bien'] ?? null,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar si ya existe una categoría con el mismo nombre
     */
    public static function verificar_nombre_duplicado(string $nombre)
    {
        return Categoria::where('nombre', $nombre)
            ->whereIn('estado', [EstadoBase::Activo->value, EstadoBase::Inactivo->value])
            ->exists();
    }
}
