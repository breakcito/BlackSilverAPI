<?php

namespace App\Views\RequerimientosAlmacenAtencion\Data;

use App\Models\KardexProducto;
use App\Models\RequerimientoAlmacenDetalleLog;
use App\Models\RequerimientoAlmacenEntregaDetalle;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoDetalleEntrega;
use Illuminate\Support\Facades\DB;

class EntregasDetalleData
{

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
            uni_lot.abreviatura as unidad_lote_abv
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

    /**
     * Obtener lotes disponibles para un producto en un almacén (FEFO/FIFO).
     */
    public static function obtener_lotes_disponibles(int $id_producto, int $id_almacen)
    {
        $sql = "
        SELECT
            lp.id AS id_lote,
            lp.correlativo,
            lp.stock_actual,
            lp.stock_actual_base,
            lp.contenido_por_presentacion,
            um.nombre AS unidad_medida,
            um.abreviatura AS unidad_medida_abv,
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            /* Cálculo de días restantes validando nulos */
            CASE 
                WHEN lp.fecha_vencimiento IS NOT NULL THEN DATEDIFF(lp.fecha_vencimiento, CURDATE()) 
                ELSE NULL 
            END AS dias_para_vencer,        
            /* Determinación del estado de vencimiento */
            CASE 
                WHEN p.es_perecible != 1 THEN 'N/A' 
                WHEN lp.fecha_vencimiento IS NULL THEN 'Sin fecha' 
                WHEN DATEDIFF(lp.fecha_vencimiento, CURDATE()) < 0 THEN 'Vencido' 
                WHEN DATEDIFF(lp.fecha_vencimiento, CURDATE()) <= p.dias_espera_vencimiento THEN 'Por vencer' 
                ELSE 'Vigente'
            END AS estado_vencimiento
        FROM
            lote_producto lp
        INNER JOIN unidad_medida um ON 
            um.id = lp.id_unidad_medida
        INNER JOIN producto p ON 
            p.id = lp.id_producto        
        WHERE
            lp.id_producto = :id_producto
            AND lp.id_almacen = :id_almacen
            AND lp.stock_actual > 0
            AND lp.estado = 'Activo'
        ORDER BY
            lp.fecha_vencimiento ASC,
            lp.fecha_hora_ingreso ASC
        ";

        return DB::select($sql, [
            'id_producto' => $id_producto,
            'id_almacen' => $id_almacen,
        ]);
    }

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
     * Crear un registro en la trazabilidad del requerimiento
     */
    public static function crear_registro_trazabilidad(
        int $id_requerimiento_detalle,
        int $id_empleado_entrega,
        string $tipo_origen,
        string $descripcion,
        string $estado,
    ) {
        return RequerimientoAlmacenDetalleLog::insertGetId([
            'id_requerimiento_almacen_detalle' => $id_requerimiento_detalle,
            'id_empleado' => $id_empleado_entrega,
            'tipo_origen' => $tipo_origen,
            'descripcion' => $descripcion,
            'estado' => $estado,
            'created_at' => now()
        ]);
    }


    /**
     * Registrar la entrega en el kardex
     */
    public static function registrar_kardex(
        int $id_lote,
        int $id_detalle_entrega,
        float $stock_anterior,
        float $stock_anterior_base,
        float $cantidad_lote,
        float $cantidad_base,
        float $nuevo_stock,
        float $nuevo_stock_base,
        string $descripcion
    ) {
        return KardexProducto::insertGetId([
            'id_lote_producto' => $id_lote,
            'id_origen' => $id_detalle_entrega,
            'tipo_origen' => OrigenMovimiento::Entrega->value,
            'tipo_movimiento' => TipoMovimiento::Salida->value,
            'stock_anterior' => $stock_anterior,
            'stock_anterior_base' => $stock_anterior_base,
            'cantidad_movimiento' => $cantidad_lote,
            'cantidad_movimiento_base' => $cantidad_base,
            'stock_resultante' => $nuevo_stock,
            'stock_resultante_base' => $nuevo_stock_base,
            'descripcion' => $descripcion,
            'created_at' => now(),
        ]);
    }
}
