<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Data;

use App\Models\RequerimientoAlmacenEntregaDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalleEntrega;
use Illuminate\Support\Facades\DB;

class EntregasDetalleData
{

    /**
     * Crear un detalle de entrega.
     * Exactamente uno de $id_lote o $id_activo_fijo debe ser provisto.
     */
    public static function crear_detalle_entrega(
        int $id_entrega,
        int $id_requerimiento_detalle,
        ?int $id_lote,
        float $cantidad_base,
        float $cantidad_lote,
        float $cantidad_requerimiento,
        float $costo_promedio,
        float $costo_unidad_lote,
        float $subtotal,
        ?int $id_activo_fijo = null,
        bool $para_mantenimiento = false,
        bool $para_produccion = false,
        ?int $id_activo_fijo_destino = null,
        ?int $id_lote_mineral = null,
    ) {
        return RequerimientoAlmacenEntregaDetalle::insertGetId([
            'id_requerimiento_almacen_entrega' => $id_entrega,
            'id_requerimiento_almacen_detalle' => $id_requerimiento_detalle,
            'id_lote_producto' => $id_lote,
            'id_activo_fijo' => $id_activo_fijo,
            'cantidad_base' => $cantidad_base,
            'cantidad_lote' => $cantidad_lote,
            'cantidad_requerimiento' => $cantidad_requerimiento,
            'costo_promedio_base' => $costo_promedio,
            'costo_unidad_lote' => $costo_unidad_lote,
            'subtotal' => $subtotal,
            'para_mantenimiento' => $para_mantenimiento,
            'para_produccion' => $para_produccion,
            'id_activo_fijo_destino' => $id_activo_fijo_destino,
            'id_lote_mineral' => $id_lote_mineral,
            'created_at' => now(),
            'estado' => EstadoRequerimientoDetalleEntrega::SinConsumir->value,
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
            
            -- datos del lote (NULL si es un activo fijo)
            raed.id_lote_producto,
            lot.correlativo,
            lot.fecha_vencimiento,
            
            -- datos del activo fijo (NULL si es un producto comun)
            raed.id_activo_fijo,
            act.correlativo AS correlativo_activo_fijo,
            
            COALESCE(prod_lote.nombre, prod_act.nombre) AS producto,
            
            /* Cálculo de días restantes (solo para lotes) */
            CASE WHEN lot.fecha_vencimiento IS NOT NULL THEN DATEDIFF(
                lot.fecha_vencimiento,
                CURRENT_DATE
            ) ELSE NULL
            END AS dias_para_vencer,

            /* Determinación del estado de vencimiento */
            CASE 
                WHEN prod_lote.es_perecible != 1 THEN 'N/A' 
                WHEN lot.fecha_vencimiento IS NULL THEN 'Sin fecha' 
                WHEN DATEDIFF(lot.fecha_vencimiento,CURRENT_DATE) < 0 THEN 'Vencido' 
                WHEN DATEDIFF(lot.fecha_vencimiento,CURRENT_DATE) <= prod_lote.dias_espera_vencimiento THEN 'Por vencer' 
                ELSE 'Vigente'
            END AS estado_vencimiento,

            raed.cantidad_base,
            raed.cantidad_lote,
            raed.cantidad_requerimiento,
            
            raed.para_mantenimiento,
            raed.para_produccion,
            raed.id_activo_fijo_destino,
            raed.id_lote_mineral,
            act_dest.correlativo AS correlativo_activo_fijo_destino,
            act_dest.codigo AS codigo_activo_fijo_destino,
            lm.codigo AS correlativo_lote_mineral,
            
            uni_lot.nombre as unidad_lote,
            uni_lot.abreviatura as unidad_lote_abv,
            COALESCE(uni_base_lote.nombre, uni_base_act.nombre) AS unidad_base,
            COALESCE(uni_base_lote.abreviatura, uni_base_act.abreviatura) AS unidad_base_abv
        FROM
            requerimiento_almacen_entrega_detalle raed
        -- lote (puede ser NULL para activos fijos)
        LEFT JOIN lote_producto lot ON
            lot.id = raed.id_lote_producto
        LEFT JOIN producto prod_lote ON
            prod_lote.id = lot.id_producto
        LEFT JOIN unidad_medida uni_base_lote ON
            uni_base_lote.id = prod_lote.id_unidad_medida_base
        LEFT JOIN unidad_medida uni_lot ON
            uni_lot.id = lot.id_unidad_medida
        -- activo fijo (puede ser NULL para productos comunes)
        LEFT JOIN activo_fijo act ON
            act.id = raed.id_activo_fijo
        LEFT JOIN producto prod_act ON
            prod_act.id = act.id_producto
        LEFT JOIN unidad_medida uni_base_act ON
            uni_base_act.id = prod_act.id_unidad_medida_base
        LEFT JOIN activo_fijo act_dest ON
            act_dest.id = raed.id_activo_fijo_destino
        LEFT JOIN lote_mineral lm ON
            lm.id = raed.id_lote_mineral
        INNER JOIN requerimiento_almacen_detalle rqd ON
            rqd.id = raed.id_requerimiento_almacen_detalle
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

        $sql .= ' ORDER BY COALESCE(lot.correlativo, act.correlativo) DESC;';

        return DB::select($sql, $params);
    }

    /**
     * Obtener un detalle de entrega específico
     */
    public static function get_detalle_entrega_by_id(int $id_detalle_entrega)
    {
        return self::get_detalles_entrega(id_detalle_entrega: $id_detalle_entrega);
    }

    /**
     * Trae TODOS los detalles de TODAS las entregas de un requerimiento.
     * Pensado para la anulación: loopeamos, agrupamos por lote / activo
     * y reingresamos stock o devolvemos la ubicación.
     */
    public static function get_detalles_por_requerimiento(int $id_requerimiento)
    {
        $sql = "
            SELECT
                raed.id AS id_entrega_detalle,
                raed.id_requerimiento_almacen_entrega,
                raed.id_requerimiento_almacen_detalle,
                raed.id_lote_producto,
                raed.id_activo_fijo,
                raed.cantidad_base,
                raed.cantidad_lote,
                raed.cantidad_requerimiento,
                raed.para_mantenimiento,
                raed.para_produccion,
                raed.id_activo_fijo_destino,
                raed.id_lote_mineral,
                raed.estado
            FROM requerimiento_almacen_entrega_detalle raed
            INNER JOIN requerimiento_almacen_entrega rae
                ON rae.id = raed.id_requerimiento_almacen_entrega
            WHERE rae.id_requerimiento_almacen = :id_requerimiento
        ";
        return DB::select($sql, ['id_requerimiento' => $id_requerimiento]);
    }

    /**
     * Marca TODOS los detalles de TODAS las entregas de un requerimiento
     * con un estado dado (uso principal: revertir entregas -> Anulado).
     */
    public static function update_estado_detalles_de_requerimiento(
        int $id_requerimiento,
        string $estado
    ): int {
        return DB::table('requerimiento_almacen_entrega_detalle AS raed')
            ->join(
                'requerimiento_almacen_entrega AS rae',
                'rae.id',
                '=',
                'raed.id_requerimiento_almacen_entrega',
            )
            ->where('rae.id_requerimiento_almacen', $id_requerimiento)
            ->update(['raed.estado' => $estado]);
    }

    /**
     * Elimina FISICAMENTE los consumos de las entregas de un requerimiento.
     *
     * Usado al anular un requerimiento con entregas que ya fueron consumidas.
     * Como el consumo no genera movimiento en Kardex (solo registra metadata),
     * reverterlo no requiere movimientos de Kardex inversos: basta con borrar
     * las filas de `requerimiento_almacen_entrega_detalle_consumo` para
     * que el saldo del lote (ya reingresado por el flujo de anulación) sea
     * consistente con el histórico.
     *
     * Solo se eliminan los consumos cuyo detalle de entrega pertenece al
     * requerimiento anulado. NO se tocan los mantenimientos ni producciones
     * que referencian esos consumos via `id_mantenimiento` / `id_lote_mineral`,
     * porque pueden tener insumos de OTROS requerimientos y no es seguro
     * eliminarlos en cascada.
     */
    public static function eliminar_consumos_de_requerimiento(int $id_requerimiento): int
    {
        return DB::table('requerimiento_almacen_entrega_detalle_consumo AS cns')
            ->join(
                'requerimiento_almacen_entrega_detalle AS raed',
                'raed.id',
                '=',
                'cns.id_requerimiento_almacen_entrega_detalle',
            )
            ->join(
                'requerimiento_almacen_entrega AS rae',
                'rae.id',
                '=',
                'raed.id_requerimiento_almacen_entrega',
            )
            ->where('rae.id_requerimiento_almacen', $id_requerimiento)
            ->delete();
    }
}
