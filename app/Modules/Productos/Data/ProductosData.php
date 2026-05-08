<?php

namespace App\Modules\Productos\Data;

use App\Models\Producto;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ProductosData
{
    /**
     * Listar todos los productos del catálogo con su categoría y unidad de medida
     */
    public static function get_productos(?int $id_producto = null)
    {
        $sql = '
            SELECT
                p.id AS id_producto,
                p.nombre,
                -- 
                p.id_categoria,
                c.nombre as categoria,
                --
                p.id_unidad_medida_base,
                um.nombre as unidad_medida_base,
                um.abreviatura as unidad_medida_abreviatura,
                -- 
                p.es_auditable,
                p.es_perecible,
                -- 
                p.stock_minimo_base,
                p.costo_promedio_base,
                -- 
                p.tiempo_espera_vencimiento,
                p.periodo_espera_vencimiento,
                p.dias_espera_vencimiento,
                -- 
                p.estado
            FROM
                producto p
            INNER JOIN categoria c ON c.id = p.id_categoria
            INNER JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
            WHERE
                1 = 1
        ';

        $params = [];
        if ($id_producto !== null) {
            $sql .= ' AND p.id = :id_producto';
            $params['id_producto'] = $id_producto;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' AND p.estado != :estado_inactivo ORDER BY p.nombre ASC';
        $params['estado_inactivo'] = EstadoBase::Inactivo->value;

        return DB::select($sql, $params);
    }

    /**
     * Obtener un producto por su ID
     */
    public static function get_producto_by_id(int $id_producto)
    {
        return self::get_productos(id_producto: $id_producto);
    }

    /**
     * Crear un nuevo producto con parámetros explícitos
     */
    public static function crear_producto(
        int $id_categoria,
        int $id_unidad_medida_base,
        string $nombre,
        bool $es_auditable,
        bool $es_perecible,
        float $stock_minimo_base,
        ?int $tiempo_espera_vencimiento,
        ?string $periodo_espera_vencimiento,
        ?int $dias_espera_vencimiento
    ) {
        return Producto::insertGetId([
            'id_categoria' => $id_categoria,
            'id_unidad_medida_base' => $id_unidad_medida_base,
            'nombre' => $nombre,
            'es_auditable' => $es_auditable,
            'es_perecible' => $es_perecible,
            'stock_minimo_base' => $stock_minimo_base,
            'tiempo_espera_vencimiento' => $tiempo_espera_vencimiento,
            'periodo_espera_vencimiento' => $periodo_espera_vencimiento,
            'dias_espera_vencimiento' => $dias_espera_vencimiento,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar si ya existe un producto con el mismo nombre
     */
    public static function existe_nombre(string $nombre): bool
    {
        return Producto::where('nombre', $nombre)
            ->where('estado', '!=', EstadoBase::Inactivo->value)
            ->exists();
    }
}
