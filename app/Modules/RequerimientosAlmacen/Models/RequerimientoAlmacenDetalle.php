<?php

namespace App\Modules\RequerimientosAlmacen\Models;

use App\Shared\Enums\EstadoRequerimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacenDetalle extends Model
{
    protected $table = 'requerimiento_almacen_detalle';

    public static function crear_detalle(
        int $id_requerimiento,
        int $id_producto,
        int $id_unidad_medida,
        float $cantidad_solicitada,
        ?string $comentario
    ) {
        return DB::table('requerimiento_almacen_detalle')->insert([
            'id_requerimiento'    => $id_requerimiento,
            'id_producto'         => $id_producto,
            'id_unidad_medida'    => $id_unidad_medida,
            'cantidad_solicitada' => $cantidad_solicitada,
            'cantidad_atendida'   => 0,
            'comentario'          => $comentario,
            'estado'              => EstadoRequerimiento::Pendiente->value
        ]);
    }
}
