<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LoteProducto extends Model
{
    protected $table = 'lote_producto';

    public $timestamps = false;

    protected $fillable = [
        'id_producto',
        'id_unidad_medida',
        'id_almacen',
        'descripcion',
        'correlativo',
        'numero_correlativo',
        'stock_actual',
        'contenido_por_presentacion',
        'stock_actual_base',
        'fecha_hora_ingreso',
        'fecha_vencimiento',
        'created_at',
        'estado',
    ];

    /**
     * Obtener lotes disponibles para un producto en un almacén (FEFO/FIFO).
     */
    public static function obtener_lotes_disponibles(int $id_producto, int $id_almacen)
    {
        $sql = "
        SELECT
            lp.id AS id_lote,
            lp.id AS id_lote_producto, /* Alias para compatibilidad con Entregas */
            lp.correlativo AS codigo_lote,
            lp.descripcion,
            lp.stock_actual,
            um.abreviatura AS unidad_medida,
            um.abreviatura AS unidad_lote, /* Alias para compatibilidad con Entregas */
            lp.stock_actual_base,
            lp.contenido_por_presentacion,
            umb.abreviatura AS unidad_base,
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
