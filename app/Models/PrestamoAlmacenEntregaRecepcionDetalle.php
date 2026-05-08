<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo que representa a la tabla utilizada para registrar los detalle de 
 * una RECEPCION de una entrega hecha por un PRESTAMO, es decir, una entrega
 * hecha por el almacen prestamista al almacen solicitante
 */
class PrestamoAlmacenEntregaRecepcionDetalle extends Model
{
    protected $table = 'prestamo_almacen_entrega_recepcion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_recepcion', // la recepcion
        'id_prestamo_almacen_entrega_detalle', // que producto de una entrega se esta recepcionando
        'id_lote_producto', // el lote del que se ajusto el stock o que se genero como nuevo
        'es_ajuste_stock', // 1 si fue un ajuste de stock o 0 si fue un registro
        'cantidad_recepcionada_base',
        'estado',
    ];

    public static function get_detalles(?int $id_detalle = null, ?int $id_recepcion = null)
    {
        $sql = '
        SELECT
            rd.id AS id_recepcion_detalle,
            rd.id_prestamo_almacen_entrega_detalle,
            rd.id_prestamo_almacen_recepcion,
            --
            pad.id_producto,
            p.nombre AS producto,
            -- unidad de medida base
            p.id_unidad_medida_base,
            ub.abreviatura AS unidad_medida_base_abv,
            rd.cantidad_recepcionada_base,
            -- cuantas unidades base hay en la unidad de la solicitud
            pad.contenido_por_presentacion,
            -- unidad de medida de la solicitud
            us.id as id_unidad_medida_pr,
            us.abreviatura as unidad_medida_pr_abv,
            (rd.cantidad_recepcionada_base / pad.contenido_por_presentacion) as cantidad_recepcionada_pr,
            --
            rd.estado
        FROM
            prestamo_almacen_entrega_recepcion_detalle rd
        INNER JOIN prestamo_almacen_entrega_detalle ed ON
            ed.id = rd.id_prestamo_almacen_entrega_detalle
        INNER JOIN prestamo_almacen_detalle pad ON
            pad.id = ed.id_prestamo_almacen_detalle
        INNER JOIN producto p ON
            p.id = pad.id_producto
        INNER JOIN unidad_medida ub ON
            ub.id = p.id_unidad_medida_base
        INNER JOIN unidad_medida us ON
            us.id = pad.id_unidad_medida
        WHERE
            1 = 1
        ';

        $params = [];
        if ($id_detalle !== null) {
            $sql .= " AND rd.id = :id_detalle";
            $params['id_detalle'] = $id_detalle;
            return DB::selectOne($sql, $params);
        }

        if ($id_recepcion !== null) {
            $sql .= " AND rd.id_prestamo_almacen_recepcion = :id_recepcion";
            $params['id_recepcion'] = $id_recepcion;
        }

        $sql .= " ORDER BY p.nombre ASC";

        return DB::select($sql, $params);
    }
}
