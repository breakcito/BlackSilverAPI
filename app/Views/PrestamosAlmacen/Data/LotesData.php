<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\LoteProducto;

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
}
