<?php

namespace App\Modules\Inventario\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KardexProducto extends Model
{
    protected $table = 'kardex_producto';

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
            CONCAT(lp.correlativo, \'-\', LPAD(lp.numero_correlativo, 3, \'0\')) AS codigo_lote,
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

    /**
     * Registrar movimiento en kardex.
     */
    public static function crear_movimiento(
        int $id_lote_producto,
        ?int $id_cabecera,
        string $codigo_movimiento,
        string $tipo_movimiento,
        float $cantidad_anterior,
        float $cantidad_movimiento,
        float $cantidad_resultante,
        ?string $glosa
    ) {
        return DB::table('kardex_producto')->insertGetId([
            'id_lote_producto'    => $id_lote_producto,
            'id_cabecera'         => $id_cabecera,
            'codigo_movimiento'   => $codigo_movimiento,
            'tipo_movimiento'     => $tipo_movimiento,
            'cantidad_anterior'   => $cantidad_anterior,
            'cantidad_movimiento' => $cantidad_movimiento,
            'cantidad_resultante' => $cantidad_resultante,
            'glosa'               => $glosa,
            'created_at'          => now(),
            'estado'              => EstadoBase::Activo->value
        ]);
    }
}
