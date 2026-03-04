<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KardexProducto extends Model
{
    protected $table = 'kardex_producto';

    public $timestamps = false;

    protected $fillable = [
        'id_lote_producto',
        'id_origen',
        'tipo_origen',
        'tipo_movimiento',
        'stock_anterior',
        'stock_anterior_base',
        'cantidad_movimiento',
        'cantidad_movimiento_base',
        'stock_resultante',
        'stock_resultante_base',
        'descripcion',
        'created_at',
    ];

    /**
     * Listar movimientos de kardex por almacén.
     */
    public static function get_kardex_by_almacen(int $id_almacen)
    {
        $sql = '
        SELECT
            k.id AS id_kardex,
            k.id_lote_producto,
            lp.id_producto,
            CONCAT(p.nombre, \' - \', um_base.abreviatura) AS producto,
            lp.correlativo as codigo_lote,
            k.tipo_origen,
            k.tipo_movimiento,
            k.stock_anterior,
            k.stock_anterior_base,
            k.cantidad_movimiento,
            k.cantidad_movimiento_base,
            k.stock_resultante,
            k.stock_resultante_base,
            k.descripcion,
            k.created_at
        FROM
            kardex_producto k
        INNER JOIN lote_producto lp ON lp.id = k.id_lote_producto
        INNER JOIN producto p ON p.id = lp.id_producto
        INNER JOIN unidad_medida um_base ON um_base.id = p.id_unidad_medida_base
        WHERE
            lp.id_almacen = :id_almacen
        ORDER BY k.created_at DESC
        ';

        return DB::select($sql, ['id_almacen' => $id_almacen]);
    }
}
