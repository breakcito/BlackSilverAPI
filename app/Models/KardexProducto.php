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
        //
        'tipo_origen', // codigo_movimiento
        'tipo_movimiento', // Entrada/Salida
        'cantidad_anterior', // 3 cajas
        'cantidad_anterior_base', // 75kg
        'cantidad_movimiento', // 1 caja
        'cantidad_movimiento_base', // 25kg
        'cantidad_resultante', // 2 cajas
        'cantidad_resultante_base', // 50kg
        'descripcion',
        //
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
            p.nombre AS producto,
            CONCAT(lp.correlativo, \'-\', DATE_FORMAT(lp.created_at, \'%y\'), \'-\', LPAD(lp.numero_correlativo, 5, \'0\')) AS codigo_lote,
            k.codigo_movimiento,
            k.tipo_movimiento,
            k.cantidad_anterior,
            k.cantidad_movimiento,
            k.cantidad_resultante,
            k.glosa,
            k.created_at,
            k.estado
        FROM
            kardex_producto k
        INNER JOIN lote_producto lp ON lp.id = k.id_lote_producto
        INNER JOIN producto p ON p.id = lp.id_producto
        WHERE
            lp.id_almacen = :id_almacen
        ORDER BY k.created_at DESC
        ';

        return DB::select($sql, ['id_almacen' => $id_almacen]);
    }
}
