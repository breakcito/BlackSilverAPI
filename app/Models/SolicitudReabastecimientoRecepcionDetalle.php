<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SolicitudReabastecimientoRecepcionDetalle extends Model
{
    protected $table = 'solicitud_reabastecimiento_recepcion_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento_recepcion',
        'id_solicitud_reabastecimiento_entrega_detalle',
        'id_lote_producto', // el lote del que se ajusto el stock o que se genero como nuevo
        'es_ajuste_stock', // 1 si fue un ajuste de stock o 0 si fue un registro
        'cantidad_recepcionada_base',
        'estado',
    ];

    /**
     * Obtener los detalles de una recepción de stock por logística
     */
    public static function get_detalles(?int $id_recep_detalle = null, ?int $id_recepcion = null)
    {
        $sql = '
        SELECT 
            rd.id as id_recepcion_detalle,
            rd.id_solicitud_reabastecimiento_recepcion,
            rd.id_solicitud_reabastecimiento_entrega_detalle,
            --
            p.id as id_producto,
            p.nombre as producto,
            --
            -- unidad de medida base
            p.id_unidad_medida_base,
            ub.abreviatura as unidad_medida_base_abv,
            rd.cantidad_recepcionada_base,
            --
            -- unidad de medida de la solicitud
            srd.id_unidad_medida as id_unidad_medida_sol,
            us.abreviatura as unidad_medida_sol_abv,
            srd.contenido_por_presentacion as contenido_por_presentacion_sol,
            (rd.cantidad_recepcionada_base / srd.contenido_por_presentacion) as cantidad_recepcionada_sol,
            --
            rd.estado
        FROM 
            solicitud_reabastecimiento_recepcion_detalle rd
        INNER JOIN solicitud_reabastecimiento_entrega_detalle ed ON ed.id = rd.id_solicitud_reabastecimiento_entrega_detalle
        INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = ed.id_solicitud_reabastecimiento_detalle
        INNER JOIN producto p ON p.id = srd.id_producto
        INNER JOIN unidad_medida ub ON ub.id = p.id_unidad_medida_base
        INNER JOIN unidad_medida us ON us.id = srd.id_unidad_medida
        WHERE 
            1 = 1
        ';

        $params = [];
        if ($id_recep_detalle !== null) {
            $sql .= " AND rd.id = :id_recep_detalle";
            $params['id_recep_detalle'] = $id_recep_detalle;
            return DB::selectOne($sql, $params);
        }

        if ($id_recepcion !== null) {
            $sql .= " AND rd.id_solicitud_reabastecimiento_recepcion = :id_recepcion";
            $params['id_recepcion'] = $id_recepcion;
        }

        $sql .= " ORDER BY p.nombre ASC;";

        return DB::select($sql, $params);
    }
}
