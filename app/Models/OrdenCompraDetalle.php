<?php

namespace App\Models;

use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraDetalle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrdenCompraDetalle extends Model
{
    protected $table = 'orden_compra_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_orden_compra',
        'id_cotizacion_detalle',
        'id_producto',
        'id_unidad_medida',
        'contenido_por_presentacion',
        'cantidad_requerida',
        'cantidad_requerida_base',
        'estado',
    ];

    // Crea un detalle de OC y retorna su ID
    public static function crear_detalle(
        int $id_orden_compra,
        int $id_cotizacion_detalle,
        int $id_producto,
        int $id_unidad_medida,
        float $contenido_por_presentacion,
        float $cantidad_requerida,
        float $cantidad_requerida_base,
    ): int {
        return self::insertGetId([
            'id_orden_compra'            => $id_orden_compra,
            'id_cotizacion_detalle'      => $id_cotizacion_detalle,
            'id_producto'                => $id_producto,
            'id_unidad_medida'           => $id_unidad_medida,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'cantidad_requerida'         => $cantidad_requerida,
            'cantidad_requerida_base'    => $cantidad_requerida_base,
            'estado'                     => EstadoOrdenCompraDetalle::Pendiente->value,
        ]);
    }

    /**
     * Obtiene los detalles de una OC con nombre de producto y unidad de medida
     */
    public static function get_detalles(int $id_orden_compra): array
    {
        return DB::select('
            SELECT
                ocd.id,
                ocd.id_orden_compra,
                ocd.id_cotizacion_detalle,
                ocd.contenido_por_presentacion,
                ocd.cantidad_requerida,
                ocd.cantidad_requerida_base,
                ocd.estado,
                --
                ocd.id_producto,
                pr.nombre   AS producto_nombre,
                --
                ocd.id_unidad_medida,
                um.nombre   AS unidad_medida_nombre,
                um.abreviatura AS unidad_medida_abv
            FROM orden_compra_detalle ocd
            INNER JOIN producto      pr ON pr.id = ocd.id_producto
            INNER JOIN unidad_medida um ON um.id = ocd.id_unidad_medida
            WHERE ocd.id_orden_compra = :id_orden_compra
            ORDER BY pr.nombre ASC
        ', ['id_orden_compra' => $id_orden_compra]);
    }
}
