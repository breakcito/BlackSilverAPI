<?php

namespace App\Views\RequerimientosAlmacenAtencion\Data;

use App\Models\RequerimientoAlmacenEntregaDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoDetalleEntrega;
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
        float $cantidad_requerimiento,
    ) {
        return RequerimientoAlmacenEntregaDetalle::insertGetId([
            'id_requerimiento_almacen_entrega' => $id_entrega,
            'id_requerimiento_almacen_detalle' => $id_requerimiento_detalle,
            'id_lote_producto' => $id_lote,
            'cantidad_base' => $cantidad_base,
            'cantidad_lote' => $cantidad_lote,
            'cantidad_requerimiento' => $cantidad_requerimiento,
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
            raed.id AS id_entrega_detalle,
            raed.id_requerimiento_almacen_detalle,
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
            raed.cantidad_base,
            -- en base a la unidad de medida base del producto
            raed.cantidad_lote,
            -- en base a la unidad de medida base del lote
            raed.cantidad_requerimiento,
            uni_lot.nombre as unidad_lote,
            uni_lot.abreviatura as unidad_lote_abv,
            uni_base.nombre as unidad_base,
            uni_base.abreviatura as unidad_base_abv
        FROM
            requerimiento_almacen_entrega_detalle raed
        INNER JOIN lote_producto lot ON
            lot.id = raed.id_lote_producto
        INNER JOIN requerimiento_almacen_detalle rqd ON
            rqd.id = raed.id_requerimiento_almacen_detalle
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
            $sql .= ' AND raed.id = :id_detalle_entrega';
            $params['id_detalle_entrega'] = $id_detalle_entrega;
            return DB::selectOne($sql, $params);
        }

        if ($id_entrega) {
            $sql .= ' AND raed.id_requerimiento_almacen_entrega = :id_entrega';
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
