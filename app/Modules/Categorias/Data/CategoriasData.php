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
            
            cat.para_transporte,
            cat.control_por_odometro,
            cat.control_por_horometro,
            cat.control_por_vueltas,
            
            -- flags
            cat.es_consumible,
            cat.es_auditable,
            
            -- destinos de uso
            cat.para_cocina,
            cat.para_mina,
            
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



}
