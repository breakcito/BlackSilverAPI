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

    public static function get_detalles_by_requerimiento(int $id_requerimiento)
    {
        $sql = '
        SELECT
            rad.id AS id_requerimiento_detalle,
            rad.id_producto,
            p.nombre AS producto,
            rad.id_unidad_medida,
            um.abreviatura AS unidad_medida,
            rad.cantidad_solicitada,
            rad.cantidad_atendida,
            rad.comentario,
            rad.comentario_rechazo,
            rad.estado
        FROM
            requerimiento_almacen_detalle rad
        INNER JOIN producto p ON p.id = rad.id_producto
        INNER JOIN unidad_medida um ON um.id = rad.id_unidad_medida
        WHERE
            rad.id_requerimiento = :id_requerimiento
        ';

        return DB::select($sql, ['id_requerimiento' => $id_requerimiento]);
    }
}
