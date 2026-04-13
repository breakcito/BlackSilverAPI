<?php

namespace App\Models;

use App\Shared\Enums\PrestamoAlmacen\EstadoDetallePrestamo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenDetalle extends Model
{
    protected $table = 'prestamo_almacen_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen',
        'id_solicitud_reabastecimiento_detalle',
        'id_producto',
        'id_unidad_medida', // unidad de medida del prestamo
        //
        // cuantas unidades de medida base hay en una unidad de medida del prestamo
        'contenido_por_presentacion',
        //
        // lo que le piden prestado
        'cantidad_solicitada',
        'cantidad_solicitada_base',
        //
        // lo que va prestando
        'cantidad_prestada',
        'cantidad_prestada_base',
        //
        // lo que va siendo repuesto por logistica
        'cantidad_repuesta',
        'cantidad_repuesta_base',
        //
        'comentario',
        'estado',
    ];

    public static function crear_detalle(
        int $id_prestamo_almacen,
        int $id_solicitud_reabastecimiento_detalle,
        int $id_producto,
        int $id_unidad_medida,
        float $contenido_por_presentacion,
        float $cantidad_solicitada,
        float $cantidad_solicitada_base,
        ?string $comentario,
    ): int {
        return PrestamoAlmacenDetalle::insertGetId([
            'id_prestamo_almacen'                    => $id_prestamo_almacen,
            'id_solicitud_reabastecimiento_detalle'  => $id_solicitud_reabastecimiento_detalle,
            'id_producto'                            => $id_producto,
            'id_unidad_medida'                       => $id_unidad_medida,
            'contenido_por_presentacion'             => $contenido_por_presentacion,
            'cantidad_solicitada'                    => $cantidad_solicitada,
            'cantidad_solicitada_base'               => $cantidad_solicitada_base,
            'cantidad_prestada'                      => 0,
            'cantidad_prestada_base'                 => 0,
            'cantidad_repuesta'                      => 0,
            'cantidad_repuesta_base'                 => 0,
            'comentario'                             => $comentario,
            'estado'                                 => EstadoDetallePrestamo::EsperandoAprobacion->value,
        ]);
    }

    /**
     * Obtiene uno o todos los detalles de un prestamo
     */
    public static function get_detalles(
        ?int $id_prestamo = null,
        ?int $id_prestamo_detalle = null
    ): array {
        $sql = '
        SELECT DISTINCT
            pad.id AS id_prestamo_detalle,
            pad.id_solicitud_reabastecimiento_detalle,
            --
            pad.id_producto,
            prod.stock_minimo,
            prod.nombre AS producto,
            prdt.nombre as producto_destino,
            (
                SELECT
                    SUM(lot.stock_actual_base)
                FROM
                    lote_producto lot
                WHERE
                    lot.id_producto = prod.id AND 
                    lot.estado = "Activo" AND 
                	lot.stock_actual_base > 0 AND
            		(lot.fecha_vencimiento > NOW() OR lot.fecha_vencimiento IS NULL) AND
                    lot.id_almacen = pa.id_almacen_prestamista
            ) as stock_disponible_base,
            --
            -- unidad de medida base del producto
            um_bs.id as id_unidad_medida_base, 
            um_bs.abreviatura AS unidad_medida_base_abv,
            --
            -- unidad de medida del prestamo
            um_pr.id as id_unidad_medida_pr, 
            um_pr.abreviatura AS unidad_medida_pr_abv,
            --
            -- cuantas unidades de medida base hay en una unidad de medida del prestamo
            pad.cantidad_solicitada,
            pad.contenido_por_presentacion, 
            pad.cantidad_solicitada_base,
            --
            -- lo que va prestando
            pad.cantidad_prestada, 
            pad.cantidad_prestada_base,
            --
            -- lo que va siendo repuesto por logistica
            pad.cantidad_repuesta, 
            pad.cantidad_repuesta_base,
            --
            pad.comentario,            
            pad.estado
        FROM
            prestamo_almacen_detalle pad
        INNER JOIN prestamo_almacen pa on pa.id = pad.id_prestamo_almacen
        INNER JOIN producto prod ON prod.id = pad.id_producto
        INNER JOIN unidad_medida um_pr ON um_pr.id = pad.id_unidad_medida
        INNER JOIN unidad_medida um_bs ON um_bs.id = prod.id_unidad_medida_base
        --
        -- joins para saber la razon del por que hay 2 productos iguales en el prestamo, pero con destino diferente
        LEFT JOIN solicitud_reabastecimiento_detalle srd on srd.id = pad.id_solicitud_reabastecimiento_detalle
        LEFT JOIN requerimiento_almacen_detalle rqd on rqd.id = srd.id_requerimiento_almacen_detalle
        LEFT JOIN producto prdt on prdt.id = rqd.id_producto_destino
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_prestamo_detalle) {
            $sql .= " AND pad.id = :id_prestamo_detalle";
            $params['id_prestamo_detalle'] = $id_prestamo_detalle;
            $res = DB::selectOne($sql, $params);
            return $res ? (array) $res : [];
        }

        if ($id_prestamo) {
            $sql .= " AND pad.id_prestamo_almacen = :id_prestamo";
            $params['id_prestamo'] = $id_prestamo;
        }

        $sql .= " ORDER BY prod.nombre ASC";
        return DB::select($sql, $params);
    }
}
