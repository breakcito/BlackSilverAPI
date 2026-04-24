<?php

namespace App\Models;

use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Enums\_Generic\TipoDespachoCompra;
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
        int $id_almacen_recepcionista,
        //
        TipoDespachoCompra $tipo_despacho,
        //
        int $tiempo_entrega,
        Periodo $tiempo_entrega_periodo,
        int $tiempo_entrega_dias,
        //
        float $contenido_por_presentacion,
        float $cantidad_requerida,
        float $cantidad_requerida_base,
        //
        float $precio_unitario,
        float $precio_unitario_base,
        //
        ?string $comentario = null,
        ?string $lugar_recojo = null,
    ): int {
        return self::insertGetId([
            'id_orden_compra' => $id_orden_compra,
            'id_cotizacion_detalle' => $id_cotizacion_detalle,
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'id_almacen_recepcionista' => $id_almacen_recepcionista,
            //
            'tipo_despacho' => $tipo_despacho->value,
            'lugar_recojo' => $lugar_recojo,
            //
            'tiempo_entrega' => $tiempo_entrega,
            'tiempo_entrega_periodo' => $tiempo_entrega_periodo->value,
            'tiempo_entrega_dias' => $tiempo_entrega_dias,
            //
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'cantidad_requerida' => $cantidad_requerida,
            'cantidad_requerida_base' => $cantidad_requerida_base,
            //
            'precio_unitario' => $precio_unitario,
            'precio_unitario_base' => $precio_unitario_base,
            //
            'comentario' => $comentario,
            'estado' => EstadoOrdenCompraDetalle::Pendiente->value,
        ]);
    }

    /**
     * Obtiene los detalles de una OC con toda la información necesaria
     */
    public static function get_detalles(int $id_orden_compra): array
    {
        return DB::select('
            SELECT
                ocd.id AS id_orden_compra_detalle,
                ocd.id_orden_compra,
                ocd.id_cotizacion_detalle,
                ocd.estado,
                --
                ocd.id_almacen_recepcionista,
                alm.nombre AS almacen_recepcionista,
                --
                ocd.tipo_despacho,
                ocd.lugar_recojo,
                --
                ocd.tiempo_entrega,
                ocd.tiempo_entrega_periodo,
                ocd.tiempo_entrega_dias,
                --
                ocd.contenido_por_presentacion,
                ocd.cantidad_requerida,
                ocd.cantidad_requerida_base,
                --
                ocd.precio_unitario,
                ocd.precio_unitario_base,
                ocd.comentario,
                --
                ocd.id_producto,
                pr.nombre AS producto_nombre,
                pr.id_unidad_medida_base,
                --
                ocd.id_unidad_medida,
                um.nombre AS unidad_medida_nombre,
                um.abreviatura AS unidad_medida_abv,
                --
                um_base.abreviatura             AS unidad_medida_base_abv
            FROM orden_compra_detalle ocd
            INNER JOIN almacen          alm ON alm.id = ocd.id_almacen_recepcionista
            INNER JOIN cotizacion_detalle cd ON cd.id  = ocd.id_cotizacion_detalle
            INNER JOIN producto          pr  ON pr.id  = ocd.id_producto
            INNER JOIN unidad_medida     um  ON um.id  = ocd.id_unidad_medida
            INNER JOIN unidad_medida um_base ON um_base.id = pr.id_unidad_medida_base
            WHERE ocd.id_orden_compra = :id_orden_compra
            ORDER BY pr.nombre ASC
        ', ['id_orden_compra' => $id_orden_compra]);
    }
}
