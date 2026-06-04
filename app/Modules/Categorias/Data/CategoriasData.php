<?php

namespace App\Modules\Categorias\Data;

use App\Models\Categoria;
use App\Models\CategoriaConsumible;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class CategoriasData
{
    /**
     * Listar o obtener una categoría
     */
    public static function get_categorias(?int $id_categoria = null, ?EstadoBase $estado = null)
    {
        $sql = '
        SELECT 
            cat.id AS id_categoria,
            cat.nombre,
            cat.descripcion,
            
            cat.tipo_producto, -- bien o servicio
            cat.clasificacion_bien, -- suministro, material o activo fijo
            
            CAST(cat.para_transporte AS UNSIGNED) AS para_transporte,
            CAST(cat.control_por_odometro AS UNSIGNED) AS control_por_odometro,
            CAST(cat.control_por_horometro AS UNSIGNED) AS control_por_horometro,
            CAST(cat.control_por_vueltas AS UNSIGNED) AS control_por_vueltas,
            
            -- flags
            CAST(cat.es_consumible AS UNSIGNED) AS es_consumible,
            CAST(cat.es_auditable AS UNSIGNED) AS es_auditable,
            
            -- destinos de uso
            CAST(cat.para_cocina AS UNSIGNED) AS para_cocina,
            CAST(cat.para_mina AS UNSIGNED) AS para_mina,
            
            -- Categorias que consumen esta categoria
            (
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        "id_categoria_consumidora", cc.id_categoria_consumidora,
                        "nombre", c_con.nombre
                    )
                )
                FROM categoria_consumible cc
                JOIN categoria c_con ON c_con.id = cc.id_categoria_consumidora
                WHERE cc.id_categoria_consumible = cat.id
            ) AS categorias_consumidoras,
            
            cat.estado
        FROM categoria cat
        WHERE 1=1 
        ';

        $params = [];

        if ($id_categoria != null) {
            $sql .= ' AND cat.id = :id_categoria';
            $params['id_categoria'] = $id_categoria;
            return DB::selectOne($sql, $params);
        }

        if ($estado != null) {
            $sql .= ' AND cat.estado = :estado';
            $params['estado'] = $estado->value;
        }

        $sql .= ' ORDER BY cat.nombre ASC';
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
     * Crear una nueva categoría con parámetros explícitos
     */
    public static function crear_categoria(
        string $nombre,
        string $tipo_producto,
        ?string $descripcion = null,
        ?string $clasificacion_bien = null,
        bool $para_transporte = false,
        bool $control_por_odometro = false,
        bool $control_por_horometro = false,
        bool $control_por_vueltas = false,
        bool $es_consumible = false,
        bool $para_cocina = false,
        bool $para_mina = false,
        bool $es_auditable = false
    ) {
        return Categoria::insertGetId([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo_producto' => $tipo_producto,
            'clasificacion_bien' => $clasificacion_bien,
            'para_transporte' => $para_transporte ? 1 : 0,
            'control_por_odometro' => $control_por_odometro ? 1 : 0,
            'control_por_horometro' => $control_por_horometro ? 1 : 0,
            'control_por_vueltas' => $control_por_vueltas ? 1 : 0,
            'es_consumible' => $es_consumible ? 1 : 0,
            'para_cocina' => $para_cocina ? 1 : 0,
            'para_mina' => $para_mina ? 1 : 0,
            'es_auditable' => $es_auditable ? 1 : 0,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Establecer las categorías consumidoras para un insumo
     */
    public static function establecer_consumidoras(int $id_categoria_consumible, array $ids_categorias_consumidoras): void
    {
        // Limpiamos relaciones previas
        CategoriaConsumible::where('id_categoria_consumible', $id_categoria_consumible)
            ->delete();

        if (empty($ids_categorias_consumidoras))
            return;

        // Insertamos nuevas relaciones
        $data = array_map(fn($id) => [
            'id_categoria_consumible' => $id_categoria_consumible,
            'id_categoria_consumidora' => (int) $id
        ], $ids_categorias_consumidoras);

        CategoriaConsumible::insert($data);
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
