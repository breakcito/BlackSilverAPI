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
        'id_almacen_recepcionista', // Obligatorio - Es el almacen que deberia recibir esos productos
        //
        'tipo_despacho', // Recojo / Envio
        'lugar_recojo', // Para el recojo es obligatorio
        // tiempos estimados
        'tiempo_entrega', // 2
        'tiempo_entrega_periodo', // Semanas
        'tiempo_entrega_dias', // 14 dias
        // 
        'cantidad_requerida', // 3 cajas
        'contenido_por_presentacion', // 2 unidades por Caja
        'cantidad_requerida_base', // 6 unidades
        //
        'precio_unitario', // S/12 la caja
        'precio_unitario_base', // S/2 por unidad
        //
        'comentario',
        //
        'estado', // Pendiente, En recepcion, Cerrada, Completada
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
            'id_orden_compra' => $id_orden_compra,
            'id_cotizacion_detalle' => $id_cotizacion_detalle,
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'cantidad_requerida' => $cantidad_requerida,
            'cantidad_requerida_base' => $cantidad_requerida_base,
            'estado' => EstadoOrdenCompraDetalle::Pendiente->value,
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
                cd.precio_unitario,
                --
                ocd.id_producto,
                pr.nombre   AS producto_nombre,
                pr.id_unidad_medida_base,
                --
                ocd.id_unidad_medida,
                um.nombre        AS unidad_medida_nombre,
                um.abreviatura   AS unidad_medida_abv,
                --
                um_base.abreviatura AS unidad_medida_base_abv
            FROM orden_compra_detalle ocd
            INNER JOIN cotizacion_detalle cd ON cd.id = ocd.id_cotizacion_detalle
            INNER JOIN producto      pr ON pr.id = ocd.id_producto
            INNER JOIN unidad_medida um ON um.id = ocd.id_unidad_medida
            INNER JOIN unidad_medida um_base ON um_base.id = pr.id_unidad_medida_base
            WHERE ocd.id_orden_compra = :id_orden_compra
            ORDER BY pr.nombre ASC
        ', ['id_orden_compra' => $id_orden_compra]);
    }
}
