<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenEntregaDetalle extends Model
{
    protected $table = 'prestamo_almacen_entrega_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_entrega',
        'id_prestamo_almacen_detalle', // detalle del prestamo que se esta entregando
        'id_lote_producto', // si se entrega un lote: completo o parcial
        'id_activo_fijo', // si se entrega un activo
        'cantidad',
        'cantidad_base',
        'comentario',
        'estado',
    ];

    /**
     * Obtiene un solo registro o todos los detalles de una entrega por prestamo
     */
    public static function get_detalles(
        ?int $id_entrega_detalle = null,
        ?int $id_entrega = null
    ) {
        $sql = '
        SELECT
            paed.id AS id_entrega_detalle,
            paed.id_prestamo_almacen_entrega,
            paed.id_prestamo_almacen_detalle,
            pad.id_solicitud_reabastecimiento_detalle,
            
            prod.id AS id_producto,
            prod.nombre AS producto,
            prod.es_perecible,
            cat.clasificacion_bien as tipo_bien,
            
            -- el lote o activo tomado para la entrega
            paed.id_lote_producto,
            lt.correlativo as lote_correlativo,
            lt.fecha_vencimiento,
            COALESCE(occ_lt.serie, lt.serie_factura_compra) as lote_serie_factura,
            COALESCE(occ_lt.numero, lt.numero_factura_compra) as lote_numero_factura,
            lt.costo_por_unidad as lote_costo_por_unidad,
            lt.id_orden_compra_detalle as lote_id_orden_compra_detalle,
            ocd_lt.id_orden_compra as lote_id_orden_compra,
            occr_lt.id_orden_compra_comprobante as lote_id_orden_compra_comprobante,
            
            paed.id_activo_fijo,
            act.correlativo as correlativo_activo_fijo,
            
            -- unidad de medida base del producto
            um_bs.id as id_unidad_medida_base, 
            um_bs.abreviatura as unidad_medida_base_abv,
            paed.cantidad_base, -- cantidad entregada segun la unidad de medida base del producto
            --
            COALESCE((
                SELECT
                    SUM(rd.cantidad_recepcionada_base)
                FROM
                    prestamo_almacen_entrega_recepcion_detalle rd
                WHERE
                    rd.id_prestamo_almacen_entrega_detalle = paed.id
            ),0) AS cantidad_total_recepcionada_base,
            
            -- unidad de medida del lote de donde salio
            lt.id_unidad_medida as id_unidad_medida_lot,
            um_lt.abreviatura AS unidad_medida_lot_abv,
            lt.contenido_por_presentacion as contenido_por_presentacion_lot, -- cuantas unidades de medida base tiene la unidad del lote
            (paed.cantidad_base / NULLIF(lt.contenido_por_presentacion, 0)) AS cantidad_lot, -- cuanto representa lo entregado para el lote
            
            -- unidad de medida del prestamo
            um_pr.id as id_unidad_medida_pr,
            um_pr.abreviatura AS unidad_medida_pr_abv,
            pad.contenido_por_presentacion as contenido_por_presentacion_pr, -- cuantas unidades base hay por una unidad del detalle del prestamo
            paed.cantidad as cantidad_prestamo, -- cantidad entregada segun la unidad de medida del prestamo
            
            paed.estado
        FROM
            prestamo_almacen_entrega_detalle paed
        LEFT JOIN lote_producto lt on lt.id = paed.id_lote_producto
        LEFT JOIN orden_compra_detalle ocd_lt ON ocd_lt.id = lt.id_orden_compra_detalle
        LEFT JOIN orden_compra_recepcion_detalle ocrd_lt ON ocrd_lt.id = lt.id_orden_compra_recepcion_detalle
        LEFT JOIN orden_compra_comprobante_recepcion occr_lt ON occr_lt.id_orden_compra_recepcion = ocrd_lt.id_orden_compra_recepcion
        LEFT JOIN orden_compra_comprobante occ_lt ON occ_lt.id = occr_lt.id_orden_compra_comprobante
        
        LEFT JOIN activo_fijo act on act.id = paed.id_activo_fijo
        INNER JOIN prestamo_almacen_detalle pad ON pad.id = paed.id_prestamo_almacen_detalle
        INNER JOIN producto prod ON prod.id = pad.id_producto
        INNER JOIN categoria cat ON cat.id = prod.id_categoria
        INNER JOIN unidad_medida um_pr ON um_pr.id = pad.id_unidad_medida
        INNER JOIN unidad_medida um_bs ON um_bs.id = prod.id_unidad_medida_base
        LEFT JOIN unidad_medida um_lt on um_lt.id = lt.id_unidad_medida
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_entrega_detalle) {
            $sql .= ' AND paed.id = :id_entrega_detalle';
            $params['id_entrega_detalle'] = $id_entrega_detalle;
            return DB::selectOne($sql, $params);
        }

        if ($id_entrega) {
            $sql .= ' AND paed.id_prestamo_almacen_entrega = :id_entrega';
            $params['id_entrega'] = $id_entrega;
        }

        $sql .= ' ORDER BY prod.nombre ASC';

        return DB::select($sql, $params);
    }
}
