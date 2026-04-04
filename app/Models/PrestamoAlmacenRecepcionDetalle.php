<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo que representa a la tabla utilizada para registrar los detalle de 
 * una RECEPCION de una entrega hecha por un PRESTAMO, es decir, una entrega
 * hecha por el almacen prestamista al almacen solicitante
 */
class PrestamoAlmacenRecepcionDetalle extends Model
{
    protected $table = 'prestamo_almacen_recepcion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_recepcion', // la recepcion
        'id_prestamo_almacen_entrega_detalle', // que producto de una entrega se esta recepcionando
        'cantidad_recepcionada_base',
        'estado',
    ];

    public static function get_detalles(?int $id_detalle = null, ?int $id_recepcion = null)
    {
        $sql = '
        SELECT
            rd.id AS id_recepcion_detalle,
            rd.id_solicitud_reabastecimiento_entrega_detalle,
            rd.id_solicitud_reabastecimiento_recepcion,
            sd.id_producto,
            p.nombre AS producto,
            -- unidad de medida base
            p.id_unidad_medida_base,
            ub.abreviatura AS unidad_medida_base_abv,
            rd.cantidad_recepcionada_base,
            -- cuantas unidades base hay en la unidad de la solicitud
            sd.contenido_por_presentacion,
            -- unidad de medida de la solicitud
            us.id as id_unidad_medida_sol,
            us.abreviatura as unidad_medida_sol_abv,
            (rd.cantidad_recepcionada_base / sd.contenido_por_presentacion) as cantidad_recepcionada_sol,
            --
            rd.estado
        FROM
            solicitud_reabastecimiento_recepcion_detalle rd
        INNER JOIN solicitud_reabastecimiento_entrega_detalle ed ON
            ed.id = rd.id_solicitud_reabastecimiento_entrega_detalle
        INNER JOIN solicitud_reabastecimiento_detalle sd ON
            sd.id = ed.id_solicitud_reabastecimiento_detalle
        INNER JOIN producto p ON
            p.id = sd.id_producto
        INNER JOIN unidad_medida ub ON
            ub.id = p.id_unidad_medida_base
        INNER JOIN unidad_medida us ON
            us.id = sd.id_unidad_medida
        WHERE
            rd.id_solicitud_reabastecimiento_recepcion = :id_recepcion
        
        ';


        $params = [];
        if ($id_detalle !== null) {
            $sql .= " AND rd.id = :id_detalle";
            $params['id_detalle'] = $id_detalle;
            return DB::selectOne($sql, $params);
        }

        if ($id_recepcion !== null) {
            $sql .= " AND rd.id_solicitud_reabastecimiento_recepcion = :id_recepcion";
            $params['id_recepcion'] = $id_recepcion;
        }

        $sql .= " ORDER BY p.nombre ASC";

        return DB::select($sql, $params);
    }
}
