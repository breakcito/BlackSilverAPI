<?php

namespace App\Views\LotesProductos\Data;

use App\Models\Almacen;
use App\Models\LoteProducto;
use Illuminate\Support\Facades\DB;

class LotesData
{
    public static function get_almacenes()
    {
        return Almacen::select('id as id_almacen', 'nombre')
            ->where('estado', "Activo")
            ->get();
    }

    /**
     * Listar lotes de un almacén.
     */
    public static function get_resumen_lotes(?int $id_almacen = null, ?int $id_lote = null)
    {
        $sql = '
        SELECT
            lp.id AS id_lote,
            lp.id_producto,
            lp.id_unidad_medida,
            p.nombre as producto,
            um_base.abreviatura as unidad_medida_base,
            c.nombre AS categoria,
            um_lote.abreviatura AS unidad_medida,
            lp.descripcion,
            lp.correlativo,
            lp.stock_actual,
            lp.contenido_por_presentacion,
            lp.stock_actual_base,
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            lp.estado,
            p.es_perecible,
            p.stock_minimo,
            p.dias_espera_vencimiento,
            /* Cálculo de días restantes */
            CASE 
                WHEN lp.fecha_vencimiento IS NOT NULL THEN 
                    DATEDIFF(lp.fecha_vencimiento,CURRENT_DATE) 
                ELSE NULL
            END AS dias_para_vencer,
            /* Determinación del estado de vencimiento */
            CASE
                WHEN p.es_perecible != 1 THEN "N/A"
                WHEN lp.fecha_vencimiento IS NULL THEN "Sin fecha"
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) < 0 THEN "Vencido"
                WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= p.dias_espera_vencimiento THEN "Por vencer"
                ELSE "Vigente"
            END AS estado_vencimiento
        FROM
            lote_producto lp
        INNER JOIN producto p ON
            p.id = lp.id_producto
        LEFT JOIN categoria c ON
            c.id = p.id_categoria
        LEFT JOIN unidad_medida um_base ON
            um_base.id = p.id_unidad_medida_base
        LEFT JOIN unidad_medida um_lote ON
            um_lote.id = lp.id_unidad_medida
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_lote !== null) {
            $sql .= ' AND lp.id = :id_lote';
            $params['id_lote'] = $id_lote;
            return DB::selectOne($sql, $params);
        }

        if ($id_almacen !== null) {
            $sql .= ' AND lp.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        $sql .= " ORDER BY lp.fecha_hora_ingreso DESC;";
        return DB::select($sql, []);
    }

    /**
     * Obtener lote por ID (para retorno post-creación).
     */
    public static function get_lote_by_id(int $id_lote)
    {
        return self::get_resumen_lotes(id_lote: $id_lote);
    }

    /**
     * Obtener stock total de un producto en un almacén.
     */
    public static function get_stock_total_producto_almacen(int $id_producto, int $id_almacen)
    {
        return DB::table('lote_producto')
            ->where('id_producto', $id_producto)
            ->where('id_almacen', $id_almacen)
            ->where('estado', 'Activo')
            ->sum('stock_actual_base');
    }

public static 

    public static function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        ?string $descripcion,
        string $correlativo,
        int $numero_correlativo,
        float $stock_inicial,
        float $contenido_por_presentacion,
        float $stock_actual_base,
        string $fecha_hora_ingreso,
        ?string $fecha_vencimiento
    ) {
        return LoteProducto::insertGetId([
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'id_almacen' => $id_almacen,
            'descripcion' => $descripcion,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'stock_inicial' => $stock_inicial,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'stock_actual_base' => $stock_actual_base,
            'fecha_hora_ingreso' => $fecha_hora_ingreso,
            'fecha_vencimiento' => $fecha_vencimiento,
            'created_at' => now(),
            'estado' => "Activo",
        ]);
    }
}
