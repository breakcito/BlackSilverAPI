<?php

namespace App\Models;

use App\Shared\Enums\EstadoDetallePrestamo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenDetalle extends Model
{
    protected $table = 'prestamo_almacen_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen',
        'id_solicitud_reabastecimiento_detalle',
        //
        'cantidad_solicitada', // lo que debe prestar: 4 cajas
        'cantidad_solicitada_base', // 8 unidades
        'cantidad_entregada', // lo que va prestando: 2 cajas
        'cantidad_entregada_base', // 4 unidades
        'cantidad_devuelta', // lo que se devolvió: 1 caja
        'cantidad_devuelta_base', // 2 unidades
        'comentario',
        'estado',
    ];

    public static function get_detalles_by_prestamo(int $id_prestamo)
    {
        $sql = '
        SELECT
            pad.id AS id_prestamo_detalle,
            pad.id_prestamo_almacen,
            pad.id_producto,
            p.nombre AS producto,
            pad.id_unidad_medida,
            um.abreviatura AS unidad_medida,
            pad.id_almacen_prestamista,
            a.nombre AS almacen_prestamista,
            pad.cantidad_solicitada,
            pad.cantidad_atendida,
            pad.cantidad_devuelta,
            pad.comentario,
            pad.comentario_rechazo,
            pad.estado
        FROM
            prestamo_almacen_detalle pad
        INNER JOIN producto p ON p.id = pad.id_producto
        INNER JOIN unidad_medida um ON um.id = pad.id_unidad_medida
        LEFT JOIN almacen a ON a.id = pad.id_almacen_prestamista
        WHERE
            pad.id_prestamo_almacen = :id_prestamo
        ';

        return DB::select($sql, ['id_prestamo' => $id_prestamo]);
    }

    public static function crear_detalle(
        int $id_prestamo,
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen_prestamista,
        float $cantidad_solicitada,
        ?string $comentario = null
    ) {
        return self::insertGetId([
            'id_prestamo_almacen' => $id_prestamo,
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'id_almacen_prestamista' => $id_almacen_prestamista,
            'cantidad_solicitada' => $cantidad_solicitada,
            'cantidad_atendida' => 0,
            'cantidad_devuelta' => 0,
            'comentario' => $comentario,
            'estado' => EstadoDetallePrestamo::Pendiente->value,
        ]);
    }
}
