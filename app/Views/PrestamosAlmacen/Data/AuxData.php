<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\Almacen;
use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use Illuminate\Support\Facades\DB;

class AuxData
{
    /**
     * Obtiene los almacenes.
     */
    public static function get_almacenes(bool $es_principal = false): array
    {
        return Almacen::get_almacenes(es_principal: $es_principal ? 1 : 0);
    }

    /**
     * Obtiene los lotes disponibles para una lista de productos en un almacén.
     */
    public static function get_lotes_disponibles(array $ids_productos, int $id_almacen)
    {
        $placeholders = implode(',', array_fill(0, count($ids_productos), '?'));

        return DB::select("
            SELECT 
                lp.id AS id_lote,
                lp.id_producto,
                lp.correlativo,
                lp.stock_actual,
                lp.stock_actual_base,
                lp.contenido_por_presentacion,
                lp.id_unidad_medida,
                pr.id_unidad_medida_base,
                uni.nombre AS unidad_medida,
                uni.abreviatura AS unidad_medida_abv,
                lp.fecha_hora_ingreso,
                lp.fecha_vencimiento,
                DATEDIFF(lp.fecha_vencimiento, NOW()) AS dias_para_vencer
            FROM 
                lote_producto lp
            INNER JOIN unidad_medida uni ON uni.id = lp.id_unidad_medida
            INNER JOIN producto pr on pr.id = lp.id_producto
            WHERE 
                lp.id_producto IN ($placeholders) AND 
                lp.id_almacen = ? AND 
                lp.stock_actual_base > 0 AND
                lp.estado = 'Activo'
            ORDER BY 
                lp.fecha_vencimiento ASC, 
                lp.created_at ASC
        ", [...$ids_productos, $id_almacen]);
    }

    /**
     * Obtener un lote por su id
     */
    public static function get_lote_by_id(int $id_lote)
    {
        return LoteProducto::select('correlativo', 'stock_actual', 'stock_actual_base')
            ->where('id', $id_lote)
            ->first();
    }

    /**
     * Actualizar stock del lote
     */
    public static function update_lote_stock(int $id_lote, float $stock_nuevo, float $stock_nuevo_base)
    {
        return LoteProducto::where('id', $id_lote)
            ->update([
                'stock_actual' => $stock_nuevo,
                'stock_actual_base' => $stock_nuevo_base
            ]);
    }

    /**
     * Registrar en el kardex
     */
    public static function registrar_kardex(
        int $id_lote,
        int $id_registro_origen,
        float $stock_anterior,
        float $stock_anterior_base,
        float $cantidad_lote,
        float $cantidad_base,
        float $nuevo_stock,
        float $nuevo_stock_base,
        string $descripcion
    ) {
        return KardexProducto::insertGetId([
            'id_lote_producto' => $id_lote,
            'id_origen' => $id_registro_origen,
            'tipo_origen' => OrigenMovimiento::Entrega->value, // Reposición es una entrega de stock
            'tipo_movimiento' => TipoMovimiento::Salida->value,
            'descripcion' => $descripcion,
            'stock_anterior' => $stock_anterior,
            'stock_anterior_base' => $stock_anterior_base,
            'cantidad_movimiento' => $cantidad_lote,
            'cantidad_movimiento_base' => $cantidad_base,
            'stock_resultante' => $nuevo_stock,
            'stock_resultante_base' => $nuevo_stock_base,
            'created_at' => now(),
        ]);
    }
}
