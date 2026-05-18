<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SolicitudReabastecimientoEntregaDetalle extends Model
{
    protected $table = 'solicitud_reabastecimiento_entrega_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_reabastecimiento_entrega',
        'id_solicitud_reabastecimiento_detalle',
        'id_lote_producto', // si se entrega un lote: completo o parcial
        'id_activo_fijo', // si se entrega un activo
        'cantidad_base',
        'cantidad_lote',
        'cantidad_solicitud',
        'estado',
    ];

    // Obtener detalles de una entrega
    public static function get_detalles(
        ?int $id_detalle_entrega = null,
        ?int $id_entrega = null
    ) {
        $sql = "
        SELECT
            red.id AS id_entrega_detalle,
            red.id_reabastecimiento_entrega,
            red.id_solicitud_reabastecimiento_detalle,
            
            srd.id_producto,
            prod.nombre AS producto,
            prod.es_perecible,
            cat.clasificacion_bien AS tipo_bien,
            
            -- el lote o activo tomado para la entrega
            red.id_lote_producto,
            lot.correlativo as lote_correlativo,
            lot.fecha_vencimiento,
            
            red.id_activo_fijo,
            act.correlativo as correlativo_activo_fijo,
            
            -- unidad de medida base
            prod.id_unidad_medida_base,
            uni_base.abreviatura AS unidad_medida_base_abv,
            red.cantidad_base, -- cantidad entregada segun la unidad base del producto
            
            -- unidad de medida del lote de donde salio
            lot.id_unidad_medida as id_unidad_medida_lot,
            uni_lot.abreviatura AS unidad_medida_lot_abv,
            lot.contenido_por_presentacion as contenido_por_presentacion_lot, -- cuantas unidades de medida base tiene la unidad del lote
            red.cantidad_lote, -- cuanto representa lo entregado para el lote
            
            -- unidad de medida de la solicitud
            srd.id_unidad_medida AS id_unidad_medida_sol,
            uni_sol.abreviatura AS unidad_medida_sol_abv,
            srd.contenido_por_presentacion AS contenido_por_presentacion_sol, -- cuantas unidades de medida base tiene la unidad de la solicitud
            red.cantidad_solicitud, -- cuanto representa lo entregado para la solicitud,
            
            COALESCE((
                SELECT
                    SUM(rd.cantidad_recepcionada_base)
                FROM
                    solicitud_reabastecimiento_recepcion_detalle rd
                WHERE
                    rd.id_solicitud_reabastecimiento_entrega_detalle = red.id
            ),0) AS cantidad_recibida_total_base,
            
            red.estado
        FROM
            solicitud_reabastecimiento_entrega_detalle red
        INNER JOIN solicitud_reabastecimiento_detalle srd ON
            srd.id = red.id_solicitud_reabastecimiento_detalle
        INNER JOIN producto prod ON
            prod.id = srd.id_producto
        INNER JOIN categoria cat ON
            cat.id = prod.id_categoria
        LEFT JOIN lote_producto lot ON
            lot.id = red.id_lote_producto
        LEFT JOIN activo_fijo act on act.id = red.id_activo_fijo
        INNER JOIN unidad_medida uni_sol ON
            uni_sol.id = srd.id_unidad_medida
        INNER JOIN unidad_medida uni_base ON
            uni_base.id = prod.id_unidad_medida_base
        LEFT JOIN unidad_medida uni_lot ON
            uni_lot.id = lot.id_unidad_medida
        WHERE
            1 = 1
        ";

        $params = [];

        if ($id_detalle_entrega) {
            $sql .= ' AND red.id = :id_detalle_entrega';
            $params['id_detalle_entrega'] = $id_detalle_entrega;
            return DB::selectOne($sql, $params);
        }

        if ($id_entrega) {
            $sql .= ' AND red.id_reabastecimiento_entrega = :id_entrega';
            $params['id_entrega'] = $id_entrega;
        }

        $sql .= " ORDER BY lot.correlativo DESC;";

        return DB::select($sql, $params);
    }
}
