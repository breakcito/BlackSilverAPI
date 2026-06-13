<?php

namespace App\Models;

use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoReposicionDetalle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Tabla que presenta CADA DETALLE/PRODUCTO de las reposiciones que
 * realiza logistica a los almacenes que fueron prestamistas,
 * con el fin de reponer el stock entregado.
 */
class PrestamoAlmacenReposicionDetalle extends Model
{
    protected $table = 'prestamo_almacen_reposicion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_reposicion', // la reposicion
        'id_prestamo_almacen_detalle', // el detalle del prestamo que se esta reponiendo
        'id_lote_producto', // el lote del almacen principal elegido para reponer
        'id_activo_fijo', // si se entrego un activo
        'cantidad_base', // cuanto representa segun la unidad de medida base del producto
        'cantidad_lote', // cuanto representa para el lote usado para la entrega
        'cantidad_prestamo', // cuanto representa para la unidad de medida del prestamo
        'estado', // En Despacho / Recepcionado
    ];

    /**
     * Metodo helper que ayuda a registrar un detalle de una reposicion.
     * Exactamente uno de $id_lote_producto o $id_activo_fijo debe ser provisto.
     */
    public static function crear_detalle(
        int $id_reposicion,
        int $id_prestamo_detalle,
        ?int $id_lote_producto,
        float $cantidad_base,
        float $cantidad_lote,
        float $cantidad_prestamo,
        ?int $id_activo_fijo = null,
    ): int {
        return self::insertGetId([
            'id_prestamo_almacen_reposicion' => $id_reposicion,
            'id_prestamo_almacen_detalle' => $id_prestamo_detalle,
            'id_lote_producto' => $id_lote_producto,
            'id_activo_fijo' => $id_activo_fijo,
            'cantidad_base' => $cantidad_base,
            'cantidad_lote' => $cantidad_lote,
            'cantidad_prestamo' => $cantidad_prestamo,
            'estado' => EstadoPrestamoReposicionDetalle::EnDespacho->value,
        ]);
    }


    /**
     * Obtiene uno o todos los detalles de una reposición.
     */
    public static function get_detalles(?int $id_reposicion = null, ?int $id_detalle = null)
    {
        $sql = '
        SELECT 
            rd.id as id_reposicion_detalle,
            rd.id_prestamo_almacen_detalle,
            
            p.id as id_producto,
            p.nombre AS producto,
            p.es_perecible,
            cat.clasificacion_bien as tipo_bien,
            
            -- unidad de medida del producto (base)
            p.id_unidad_medida_base,
            um_bs.nombre as unidad_medida_base,
            um_bs.abreviatura AS unidad_medida_base_abv,
            rd.cantidad_base,
            
            -- lote o activo de salida
            rd.id_lote_producto,
            lt.correlativo AS lote_correlativo,
            COALESCE(occ_lt.serie, lt.serie_factura_compra) as lote_serie_factura,
            COALESCE(occ_lt.numero, lt.numero_factura_compra) as lote_numero_factura,
            lt.costo_por_unidad as lote_costo_por_unidad,
            lt.id_orden_compra_detalle as lote_id_orden_compra_detalle,
            ocd_lt.id_orden_compra as lote_id_orden_compra,
            occr_lt.id_orden_compra_comprobante as lote_id_orden_compra_comprobante,
            
            rd.id_activo_fijo,
            act.correlativo as correlativo_activo_fijo,
            
            -- unidad de medida del lote usado para la entrega de reposicion
            um_lt.id as id_unidad_medida_lote,
            um_lt.nombre as unidad_medida_lote,
            um_lt.abreviatura as unidad_medida_lote_abv,
            rd.cantidad_lote,
            
            -- unidad de medida del prestamo (solicitada)
            pd.id_unidad_medida as id_unidad_medida_pr,
            rd.cantidad_prestamo,
            
            rd.estado
        FROM 
            prestamo_almacen_reposicion_detalle rd
        INNER JOIN prestamo_almacen_detalle pd ON pd.id = rd.id_prestamo_almacen_detalle
        LEFT JOIN lote_producto lt ON lt.id = rd.id_lote_producto
        LEFT JOIN orden_compra_detalle ocd_lt ON ocd_lt.id = lt.id_orden_compra_detalle
        LEFT JOIN orden_compra_recepcion_detalle ocrd_lt ON ocrd_lt.id = lt.id_orden_compra_recepcion_detalle
        LEFT JOIN orden_compra_comprobante_recepcion occr_lt ON occr_lt.id_orden_compra_recepcion = ocrd_lt.id_orden_compra_recepcion
        LEFT JOIN orden_compra_comprobante occ_lt ON occ_lt.id = occr_lt.id_orden_compra_comprobante
        INNER JOIN producto p ON p.id = pd.id_producto
        INNER JOIN categoria cat on cat.id = p.id_categoria
        INNER JOIN unidad_medida um_bs ON um_bs.id = p.id_unidad_medida_base
        LEFT JOIN lote_producto lt ON lt.id = rd.id_lote_producto
        LEFT JOIN unidad_medida um_lt ON um_lt.id = lt.id_unidad_medida
        LEFT JOIN activo_fijo act ON act.id = rd.id_activo_fijo
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_detalle !== null) {
            $sql .= ' AND rd.id = :id_detalle';
            $params['id_detalle'] = $id_detalle;
            return DB::selectOne($sql, $params);
        }

        if ($id_reposicion !== null) {
            $sql .= ' AND rd.id_prestamo_almacen_reposicion = :id_reposicion';
            $params['id_reposicion'] = $id_reposicion;
        }

        $sql .= ' ORDER BY p.nombre ASC';

        return DB::select($sql, $params);
    }
}
