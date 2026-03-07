<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
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
     * Listar lotes de un almacén.
     */
    public static function get_lotes_by_almacen(int $id_almacen)
    {
        $sql = '
        SELECT
            lp.id AS id_lote,
            lp.id_producto,
            CONCAT(p.nombre, \' - \', COALESCE(um_base.abreviatura, \'S/U\')) AS producto,
            c.nombre AS categoria,
            lp.id_unidad_medida,
            um_lote.abreviatura AS unidad_medida,
            lp.id_almacen,
            lp.descripcion,
            lp.correlativo as codigo_lote,
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
                WHEN lp.fecha_vencimiento IS NOT NULL THEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE)
                ELSE NULL
            END as dias_para_vencer,
            /* Stock total del producto en este almacén */
            (SELECT SUM(stock_actual_base) 
             FROM lote_producto 
             WHERE id_producto = lp.id_producto 
               AND id_almacen = lp.id_almacen 
               AND estado = :estado_sub) as stock_total_almacen
        FROM
            lote_producto lp
        INNER JOIN producto p ON p.id = lp.id_producto
        LEFT JOIN categoria c ON c.id = p.id_categoria
        LEFT JOIN unidad_medida um_base ON um_base.id = p.id_unidad_medida_base
        LEFT JOIN unidad_medida um_lote ON um_lote.id = lp.id_unidad_medida
        WHERE
            lp.id_almacen = :id_almacen AND
            lp.estado = :estado
        ORDER BY lp.fecha_hora_ingreso DESC
        ';

        return DB::select($sql, [
            'id_almacen' => $id_almacen,
            'estado' => EstadoBase::Activo->value,
            'estado_sub' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Obtener lote por ID (para retorno post-creación).
     */
    public static function get_lote_by_id(int $id)
    {
        $sql = '
        SELECT
            lp.id AS id_lote,
            lp.id_producto,
            CONCAT(p.nombre, \' - \', COALESCE(um_base.abreviatura, \'S/U\')) AS producto,
            c.nombre AS categoria,
            lp.id_unidad_medida,
            um_lote.abreviatura AS unidad_medida,
            lp.id_almacen,
            lp.descripcion,
            lp.correlativo as codigo_lote,
            lp.stock_actual,
            lp.contenido_por_presentacion,
            lp.stock_actual_base,
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            lp.estado,
            p.es_perecible,
            p.stock_minimo,
            p.dias_espera_vencimiento,
            DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) as dias_para_vencer,
            (SELECT SUM(stock_actual_base) 
             FROM lote_producto 
             WHERE id_producto = lp.id_producto 
               AND id_almacen = lp.id_almacen 
               AND estado = \'Activo\') as stock_total_almacen
        FROM
            lote_producto lp
        INNER JOIN producto p ON p.id = lp.id_producto
        LEFT JOIN categoria c ON c.id = p.id_categoria
        LEFT JOIN unidad_medida um_base ON um_base.id = p.id_unidad_medida_base
        LEFT JOIN unidad_medida um_lote ON um_lote.id = lp.id_unidad_medida
        WHERE
            lp.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }

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
