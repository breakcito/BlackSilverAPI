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
            lp.estado
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
            lp.estado
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


}
