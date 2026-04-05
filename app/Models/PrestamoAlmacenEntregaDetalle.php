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
        'id_prestamo_almacen_detalle',
        'id_lote_salida',
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
            pad.id_solicitud_reabastecimiento_detalle,
            paed.id_prestamo_almacen_detalle,
            --
            prod.id AS id_producto,
            prod.nombre AS producto,
            --
            -- el lote tomado para la entrega
            paed.id_lote_salida as id_lote_producto,
            lt.correlativo as lote_correlativo,
            --
            -- unidad de medida base del producto
            um_bs.id as id_unidad_medida_base, 
            um_bs.abreviatura as unidad_medida_base_abv,
            paed.cantidad_base, -- cantidad entregada segun la unidad de medida base del producto
            --
            -- unidad de medida del lote de donde salio
            lt.id_unidad_medida as id_unidad_medida_lot,
            um_lt.abreviatura AS unidad_medida_lot_abv,
            lt.contenido_por_presentacion as contenido_por_presentacion_lot, -- cuantas unidades de medida base tiene la unidad del lote
            (paed.cantidad_base / lt.contenido_por_presentacion) AS cantidad_lot, -- cuanto representa lo entregado para el lote
            --
            -- unidad de medida del prestamo
            um_pr.id as id_unidad_medida_pr,
            um_pr.abreviatura AS unidad_medida_pr_abv,
            pad.contenido_por_presentacion as contenido_por_presentacion_pr, -- cuantas unidades base hay por una unidad del detalle del prestamo
            paed.cantidad as cantidad_prestamo, -- cantidad entregada segun la unidad de medida del prestamo
            --
            COALESCE((
                SELECT
                    SUM(rd.cantidad_recepcionada_base)
                FROM
                    prestamo_almacen_recepcion_detalle rd
                WHERE
                    rd.id_prestamo_almacen_entrega_detalle = paed.id
            ),0) AS cantidad_recibida_total_base,
            --
            paed.estado
        FROM
            prestamo_almacen_entrega_detalle paed
        INNER JOIN lote_producto lt on lt.id = paed.id_lote_salida
        INNER JOIN prestamo_almacen_detalle pad ON pad.id = paed.id_prestamo_almacen_detalle
        INNER JOIN producto prod ON prod.id = pad.id_producto
        INNER JOIN unidad_medida um_pr ON um_pr.id = pad.id_unidad_medida
        INNER JOIN unidad_medida um_bs ON um_bs.id = prod.id_unidad_medida_base
        INNER JOIN unidad_medida um_lt on um_lt.id = lt.id_unidad_medida
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
