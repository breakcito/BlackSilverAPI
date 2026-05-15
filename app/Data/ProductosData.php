<?php

namespace App\Data;

use App\Models\Producto;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\TipoBien;
use Illuminate\Support\Facades\DB;

class ProductosData
{

    /**
     * Listado de productos
     */
    public static function get_productos(
        ?EstadoBase $estado = EstadoBase::Activo,
        ?bool $con_categorias_consumidoras = false,
        ?TipoBien $tipo_bien_excluido = null
    ) {
        $sql = '
        SELECT
            p.id as id_producto,
            p.nombre as nombre,
            p.prefijo,
            -- categoria
            p.id_categoria,
            c.nombre AS categoria,
            c.es_consumible,
            -- las categorias que consumen esta categoria del producto
            CASE
            	WHEN :con_categorias_consumidoras = 1 THEN
                (
                    SELECT 
                        GROUP_CONCAT(DISTINCT cc.id_categoria_consumidora)
                    FROM categoria_consumible cc
                    WHERE 
                        cc.id_categoria_consumible = c.id
                )
                ELSE NULL
            END AS ids_categorias_consumidoras, 
            
            p.stock_minimo_base, -- cuanto deberia tener como minimo
            
            -- unidad base
            p.id_unidad_medida_base,
            um_base.nombre as unidad_medida_base,
            um_base.abreviatura as unidad_medida_base_abv,
            
            -- indicadores del producto
            p.es_perecible,
            p.es_auditable,
            
            -- costos
            p.costo_promedio_base,

            -- cuantos dias antes debemos alertar el vencimiento de productos
            p.dias_espera_vencimiento
        FROM producto p
        INNER JOIN categoria c ON
            c.id = p.id_categoria
        INNER JOIN unidad_medida um_base ON
            um_base.id = p.id_unidad_medida_base
        WHERE
            p.estado = :estado
        ';

        $params = [];

        $params['estado'] = $estado->value;
        $params['con_categorias_consumidoras'] = $con_categorias_consumidoras ? 1 : 0;

        if ($tipo_bien_excluido) {
            $sql .= ' AND c.clasificacion_bien <> :tipo_bien_excluido';
            $params['tipo_bien_excluido'] = $tipo_bien_excluido->value;
        }

        $sql .= ' ORDER BY p.nombre ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtiene el costo promedio del producto
     */
    public static function get_costo_promedio_producto(int $id_producto): float
    {
        $sql = '
        SELECT
            pr.costo_promedio_base
        FROM
            producto pr
        WHERE pr.id = :id_producto
        ';

        $resultado = DB::selectOne($sql, [
            'id_producto' => $id_producto
        ]);

        return (float) ($resultado?->costo_promedio_base ?? 0.0);
    }

    /**
     * Actualiza el costo promedio por unidad base de un producto al registrar una nueva compra.
     *
     * Fórmula:
     *   nuevo_promedio = (costo_actual + suma(nuevos_costos)) / (1 + cantidad_nuevos)
     *
     * @param int   $id_producto       ID del producto a actualizar.
     * @param array $nuevos_costos_base Lista de precios por unidad base de la nueva compra.
     *                                  Ej: [3.2, 3.2] si se compraron 2 lotes del mismo producto.
     */
    public static function actualizar_costo_promedio(int $id_producto, array $nuevos_costos_base): void
    {
        if (empty($nuevos_costos_base)) {
            return;
        }

        // 1. Obtener el producto actual con su log
        $producto = Producto::find($id_producto);
        if (!$producto) {
            return;
        }

        $costo_actual = (float) $producto->costo_promedio_base;

        // 2. Calcular nuevo promedio: (actual + nuevo1 + nuevo2 + ...) / (1 + N)
        $suma_nuevos = array_sum($nuevos_costos_base);
        $divisor = 1 + count($nuevos_costos_base);
        $nuevo_promedio = round(($costo_actual + $suma_nuevos) / $divisor, 4);

        // 3. Si hay variación, registrar en el log
        $data_update = [
            'costo_promedio_base' => $nuevo_promedio
        ];

        if ($costo_actual !== (float) $nuevo_promedio) {
            $log_actual = $producto->costo_promedio_base_log ?? [];
            $nuevo_registro = [
                'costo_promedio_anterior' => $costo_actual,
                'costo_promedio_resultante' => $nuevo_promedio,
                'created_at' => now()->toDateTimeString(),
            ];

            $log_actual[] = $nuevo_registro;
            $data_update['costo_promedio_base_log'] = $log_actual;
        }

        $producto->update($data_update);
    }
}
