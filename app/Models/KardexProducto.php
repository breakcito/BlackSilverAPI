<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
