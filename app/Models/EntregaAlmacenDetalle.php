<?php

namespace App\Modules\RequerimientosAlmacen\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EntregaAlmacenDetalle extends Model
{
    protected $table = 'entrega_almacen_detalle';

    public static function crear_detalle_entrega(
        int $id_entrega_almacen,
        int $id_requerimiento_almacen_detalle,
        int $id_lote,
        float $cantidad
    ) {
        return DB::table('entrega_almacen_detalle')->insert([
            'id_entrega_almacen'               => $id_entrega_almacen,
            'id_requerimiento_almacen_detalle' => $id_requerimiento_almacen_detalle,
            'id_lote'                          => $id_lote,
            'cantidad'                         => $cantidad
        ]);
    }
}
