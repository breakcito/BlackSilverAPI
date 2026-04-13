<?php

namespace App\Modules\KardexProductos\Data;

use Illuminate\Support\Facades\DB;

class KardexData
{
    /**
     * Listar movimientos de kardex por almacén y periodo.
     */
    public static function get_resumen_kardex(int $id_almacen, int $mes, int $yearcito)
    {
        $sql = '
        SELECT
            k.id AS id_kardex,
            k.id_lote_producto,
            lp.id_producto,
            cat.nombre as categoria,
            p.nombre AS producto,
            lp.correlativo,
            um_lote.nombre as unidad_lote,
            um_lote.abreviatura as unidad_lote_abv,
            um_base.nombre as unidad_base,
            um_base.abreviatura as unidad_base_abv,
            k.tipo_movimiento,
            k.tipo_origen,
            k.descripcion,
            k.stock_anterior,
            k.stock_anterior_base,
            k.cantidad_movimiento,
            k.cantidad_movimiento_base,
            k.stock_resultante,
            k.stock_resultante_base,
            k.created_at
        FROM
            kardex_producto k
        INNER JOIN lote_producto lp ON lp.id = k.id_lote_producto
        INNER JOIN producto p ON p.id = lp.id_producto
        INNER JOIN categoria cat on cat.id = p.id_categoria
        INNER JOIN unidad_medida um_base ON um_base.id = p.id_unidad_medida_base
        INNER JOIN unidad_medida um_lote ON um_lote.id = lp.id_unidad_medida
        WHERE
            lp.id_almacen = :id_almacen AND
            MONTH(k.created_at) = :mes AND
            YEAR(k.created_at) = :yearcito
        ORDER BY k.created_at DESC
        ';

        return DB::select($sql, [
            'id_almacen' => $id_almacen,
            'mes' => $mes,
            'yearcito' => $yearcito
        ]);
    }
}
