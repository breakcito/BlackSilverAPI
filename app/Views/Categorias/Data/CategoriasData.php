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
        $query = Categoria::select(
            'id AS id_categoria',
            'nombre',
            'descripcion',
            'tipo_requerimiento',
            'clasificacion_bien',
            'es_consumible',
            'para_cocina',
            'para_mina',
            'estado'
        )->selectRaw('(
            SELECT GROUP_CONCAT(c_con.nombre SEPARATOR ", ")
            FROM categoria_consumible cc
            JOIN categoria c_con ON c_con.id = cc.id_categoria_consumidora
            WHERE cc.id_categoria_consumible = categoria.id
        ) as nombres_consumidoras')
            ->selectRaw('(
            SELECT GROUP_CONCAT(cc.id_categoria_consumidora)
            FROM categoria_consumible cc
            WHERE cc.id_categoria_consumible = categoria.id
        ) as ids_categorias_consumidoras');

        if ($id_categoria !== null) {
            return $query->where('id', $id_categoria)->first();
        }

        return $query->where('estado', EstadoBase::Activo->value)
            ->orderBy('nombre', 'ASC')
            ->get();
    }

    /**
     * Obtener una categoría por su ID
     */
    public static function get_categoria_by_id(int $id_categoria)
    {
        return self::get_categorias(id_categoria: $id_categoria);
    }

    /**
     * Crear una nueva categoría con parámetros explícitos
     */
    public static function crear_categoria(
        string $nombre,
        string $tipo_requerimiento,
        ?string $descripcion = null,
        ?string $clasificacion_bien = null,
        bool $es_consumible = false,
        bool $para_cocina = false,
        bool $para_mina = false
    ) {
        return Categoria::insertGetId([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo_requerimiento' => $tipo_requerimiento,
            'clasificacion_bien' => $clasificacion_bien,
            'es_consumible' => $es_consumible ? 1 : 0,
            'para_cocina' => $para_cocina ? 1 : 0,
            'para_mina' => $para_mina ? 1 : 0,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Establecer las categorías consumidoras para un insumo
     */
    public static function establecer_consumidoras(int $id_categoria_consumible, array $ids_categorias_consumidoras): void
    {
        // Limpiamos relaciones previas
        DB::table('categoria_consumible')
            ->where('id_categoria_consumible', $id_categoria_consumible)
            ->delete();

        if (empty($ids_categorias_consumidoras)) return;

        // Insertamos nuevas relaciones
        $data = array_map(fn($id) => [
            'id_categoria_consumible' => $id_categoria_consumible,
            'id_categoria_consumidora' => (int) $id
        ], $ids_categorias_consumidoras);

        DB::table('categoria_consumible')->insert($data);
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
