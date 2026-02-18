<?php

namespace App\Modules\Inventario\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KardexProducto extends Model
{
    protected $table = 'kardex_producto';

    /**
     * Listar movimientos de kardex por lote.
     */
    public static function get_kardex_by_lote(int $id_lote_producto)
    {
        $sql = '
        SELECT
            k.id AS id_kardex,
            k.id_lote_producto,
            k.id_cabecera,
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
        WHERE
            k.id_lote_producto = :id_lote
        ORDER BY k.created_at DESC
        ';

        return DB::select($sql, ['id_lote' => $id_lote_producto]);
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
