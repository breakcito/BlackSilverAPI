<?php

namespace App\Views\RequerimientosAlmacenAtencion\Data;

use App\Models\Labor;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class EntregasDetalleData
{

    /**
     * Obtener lotes disponibles para un producto en un almacén (FEFO/FIFO).
     */
    public static function obtener_lotes_disponibles(int $id_producto, int $id_almacen)
    {
        $sql = "
        SELECT
            lp.id AS id_lote,
            lp.correlativo,
            lp.descripcion,
            lp.stock_actual,
            lp.stock_actual_base,
            lp.contenido_por_presentacion,
            um.nombre AS unidad_medida,
            um.abreviatura AS unidad_medida_abv,
            umb.nombre AS unidad_medida_base,
            umb.abreviatura AS unidad_medida_base_abv,
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            DATEDIFF(lp.fecha_vencimiento, CURDATE()) AS dias_para_vencer
        FROM
            lote_producto lp
        INNER JOIN unidad_medida um ON um.id = lp.id_unidad_medida
        INNER JOIN producto p ON p.id = lp.id_producto
        INNER JOIN unidad_medida umb ON umb.id = p.id_unidad_medida_base
        WHERE
            lp.id_producto = :id_producto
            AND lp.id_almacen = :id_almacen
            AND lp.stock_actual > 0
            AND lp.estado = 'Activo'
        ORDER BY
            lp.fecha_vencimiento ASC,
            lp.fecha_hora_ingreso ASC
        ";

        return DB::select($sql, [
            'id_producto' => $id_producto,
            'id_almacen' => $id_almacen,
        ]);
    }
}
