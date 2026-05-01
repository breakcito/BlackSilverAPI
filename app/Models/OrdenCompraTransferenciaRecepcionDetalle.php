<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo que representa a la tabla utilizada para registrar los detalle de 
 * una RECEPCION de una transferencua hecha tras hacer la recepcion de una 
 * orden de compra en un almacen al que no estaba inicialmente destinado
 */
class OrdenCompraTransferenciaRecepcionDetalle extends Model
{
    protected $table = 'orden_compra_transferencia_recepcion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_orden_compra_transferencia_recepcion', // la recepcion
        'id_orden_compra_transferencia_detalle', // que producto de una entrega se esta recepcionando
        //
        'cantidad_recepcionada_base',
        //
        'estado', // Recepcionado / Recepcionado parcialmente
    ];

    public static function get_detalles(?int $id_detalle = null, ?int $id_recepcion = null)
    {
        $sql = '
        SELECT
            rd.id AS id_recepcion_detalle,
            rd.id_orden_compra_transferencia_recepcion,
            rd.id_orden_compra_transferencia_detalle,
            --
            ocd.id_producto,
            prod.nombre AS producto,
            -- unidad de mtdida base
            prod.id_unidad_medida_base,
            umb.abreviatura AS unidad_medida_base_abv,
            rd.cantidad_recepcionada_base,
            -- cuantas unidades base hay en la unidad de la oc
            ocd.contenido_por_presentacion,
            -- unidad de medida de la oc
            umc.id as id_unidad_medida_oc,
            umc.abreviatura as unidad_medida_oc_abv,
            (rd.cantidad_recepcionada_base / ocd.contenido_por_presentacion) as cantidad_recepcionada_oc,
            --
            rd.estado
        FROM
            orden_compra_transferencia_recepcion_detalle rd
        -- 
        INNER JOIN orden_compra_transferencia_detalle td ON
            td.id = rd.id_orden_compra_transferencia_detalle
        INNER JOIN orden_compra_recepcion_detalle orcd ON
            orcd.id = td.id_orden_compra_recepcion_detalle
        INNER JOIN orden_compra_detalle ocd ON 
        	ocd.id = orcd.id_orden_compra_detalle
        INNER JOIN producto prod ON
            prod.id = ocd.id_producto
        INNER JOIN unidad_medida umb ON
            umb.id = prod.id_unidad_medida_base
        INNER JOIN unidad_medida umc ON
            umc.id = ocd.id_unidad_medida
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
            $sql .= " AND rd.id_orden_compra_transferencia_recepcion = :id_recepcion";
            $params['id_recepcion'] = $id_recepcion;
        }

        $sql .= " ORDER BY prod.nombre ASC";

        return DB::select($sql, $params);
    }
}
