<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use App\Models\SolicitudReabastecimientoEntregaDetalle;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoDetalleEntrega;
use Illuminate\Support\Facades\DB;

class EntregasDetalleData
{

    /**
     * Crear un detalle de  entrega
     */
    public static function crear_detalle_entrega(
        int $id_entrega,
        int $id_requerimiento_detalle,
        int $id_lote,
        float $cantidad_base,
        float $cantidad_lote,
        float $cantidad_solicitud,
    ) {
        return SolicitudReabastecimientoEntregaDetalle::insertGetId([
            'id_reabastecimiento_entrega' => $id_entrega,
            'id_solicitud_reabastecimiento_detalle' => $id_requerimiento_detalle,
            'id_lote_producto' => $id_lote,
            'cantidad_base' => $cantidad_base,
            'cantidad_lote' => $cantidad_lote,
            'cantidad_solicitud' => $cantidad_solicitud,
            'created_at' => now(),
            'estado' => EstadoDetalleEntrega::Entregado->value,
        ]);
    }


    /**
     * Obtener los detalles de una entrega
     */
    public static function get_detalles_entrega(?int $id_entrega = null, ?int $id_detalle_entrega = null)
    {
        $sql = "
        SELECT
            red.id AS id_entrega_detalle,
            red.id_solicitud_reabastecimiento_detalle,
            lot.correlativo,
            lot.fecha_vencimiento,
            prod.nombre as producto,
            /* Cálculo de días restantes */
            CASE WHEN lot.fecha_vencimiento IS NOT NULL THEN DATEDIFF(
                lot.fecha_vencimiento,
                CURRENT_DATE
            ) ELSE NULL
            END AS dias_para_vencer,
            /* Determinación del estado de vencimiento */
            CASE 
                WHEN prod.es_perecible != 1 THEN 'N/A' 
                WHEN lot.fecha_vencimiento IS NULL THEN 'Sin fecha' 
                WHEN DATEDIFF(lot.fecha_vencimiento,CURRENT_DATE) < 0 THEN 'Vencido' 
                WHEN DATEDIFF(lot.fecha_vencimiento,CURRENT_DATE) <= prod.dias_espera_vencimiento THEN 'Por vencer' 
                ELSE 'Vigente'
            END AS estado_vencimiento,
            red.cantidad_base,
            -- en base a la unidad de medida base del producto
            red.cantidad_lote,
            -- en base a la unidad de medida base del lote
            red.cantidad_solicitud,
            lot.id_unidad_medida as id_unidad_medida_lote, 
            prod.id_unidad_medida_base,
            uni_lot.nombre as unidad_lote,
            uni_lot.abreviatura as unidad_lote_abv,
            uni_base.nombre as unidad_base,
            uni_base.abreviatura as unidad_base_abv
        FROM
            reabastecimiento_entrega_detalle red
        INNER JOIN lote_producto lot ON
            lot.id = red.id_lote_producto
        INNER JOIN solicitud_reabastecimiento_detalle srd ON
            srd.id = red.id_solicitud_reabastecimiento_detalle
        INNER JOIN producto prod ON
            prod.id = lot.id_producto
        INNER JOIN unidad_medida uni_base ON
            uni_base.id = prod.id_unidad_medida_base
        INNER JOIN unidad_medida uni_lot ON
            uni_lot.id = lot.id_unidad_medida
        WHERE 1 = 1
        ";

        $params = [];

        // Si buscamos un detalle específico, devolvemos un único objeto
        if ($id_detalle_entrega) {
            $sql .= ' AND red.id = :id_detalle_entrega';
            $params['id_detalle_entrega'] = $id_detalle_entrega;
            return DB::selectOne($sql, $params);
        }

        if ($id_entrega) {
            $sql .= ' AND red.id_reabastecimiento_entrega = :id_entrega';
            $params['id_entrega'] = $id_entrega;
        }

        $sql .= ' ORDER BY lot.correlativo DESC;';

        return DB::select($sql, $params);
    }

    /**
     * Obtener un detalle de entrega específico
     */
    public static function get_detalle_entrega_by_id(int $id_detalle_entrega)
    {
        return self::get_detalles_entrega(id_detalle_entrega: $id_detalle_entrega);
    }
}
