<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;

class LotesData
{
    /**
     * Obtiene los lotes disponibles del almacen prestamista con la intencion de
     * poder ajustar su stock luego de que logistica le haya repuesto el stock prestado.
     */
    public static function get_lotes_disponibles(int $id_almacen, array $ids_productos)
    {
        return LoteProducto::get_lotes_disponibles($id_almacen, $ids_productos);
    }

    /**
     * Obtener datos basicos de un lote por su id
     */
    public static function get_lote_simple_by_id(int $id_lote)
    {
        return LoteProducto::get_lote_simple_by_id($id_lote);
    }

    /**
     * Actualiza el stock de un lote en caso de decidir ajustar stock luego de una 
     * reposicion por parte de logistica al almacen prestamista
     */
    public static function update_stock_lote(int $id_lote, float $stock_nuevo, float $stock_nuevo_base)
    {
        return LoteProducto::update_stock($id_lote, $stock_nuevo, $stock_nuevo_base);
    }

    /**
     * Registrar en el kardex
     */
    public static function registrar_kardex(
        int $id_lote,
        int $id_origen,
        //
        string $descripcion,
        //
        float $stock_anterior,
        float $stock_anterior_base,
        float $cantidad_movimiento,
        float $cantidad_movimiento_base,
        float $nuevo_stock,
        float $nuevo_stock_base,
    ) {
        return KardexProducto::registrar_kardex(
            $id_lote,
            $id_origen,
            //
            TipoMovimiento::Salida,
            OrigenMovimiento::Entrega,
            $descripcion,
            //
            $stock_anterior,
            $stock_anterior_base,
            $cantidad_movimiento,
            $cantidad_movimiento_base,
            $nuevo_stock,
            $nuevo_stock_base,
        );
    }
}
