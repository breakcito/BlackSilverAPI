<?php

namespace App\Models;

use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use Illuminate\Database\Eloquent\Model;

class KardexProducto extends Model
{
    protected $table = 'kardex_producto';

    public $timestamps = false;

    protected $fillable = [
        'id_lote_producto',
        'id_origen',
        'tipo_movimiento', // Ingreso | Salida
        'tipo_origen', // titulo breve (Nuevo lote, recepcion, entrega, etc)
        'descripcion', // descripcion breve del movimiento relacionada al tipo de origen
        'stock_anterior',
        'stock_anterior_base',  // cuando habia antes en base a la unidad de medida del producto
        'cantidad_movimiento',
        'cantidad_movimiento_base', // cuando se saco/ingreso en base a la unidad de medida del producto
        'stock_resultante',
        'stock_resultante_base', // cuanto hay ahora en base a la unidad de medida del producto
        'created_at', // cuando se registro el movimiento
    ];

    public static function registrar_kardex(
        int $id_lote,
        int $id_origen,
        //
        TipoMovimiento $tipo_movimiento,
        OrigenMovimiento $tipo_origen,
        string $descripcion,
        //
        float $stock_anterior,
        float $stock_anterior_base,
        float $cantidad_movimiento,
        float $cantidad_movimiento_base,
        float $nuevo_stock,
        float $nuevo_stock_base,
    ) {
        return KardexProducto::insertGetId([
            'id_lote_producto' => $id_lote,
            'id_origen' => $id_origen,
            //
            'tipo_movimiento' => $tipo_movimiento->value,
            'tipo_origen' => $tipo_origen->value,
            'descripcion' => $descripcion,
            //
            'stock_anterior' => $stock_anterior,
            'stock_anterior_base' => $stock_anterior_base,
            'cantidad_movimiento' => $cantidad_movimiento,
            'cantidad_movimiento_base' => $cantidad_movimiento_base,
            'stock_resultante' => $nuevo_stock,
            'stock_resultante_base' => $nuevo_stock_base,
            'created_at' => now(),
        ]);
    }
}
