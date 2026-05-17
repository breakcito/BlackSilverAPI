<?php

namespace App\Models;

use App\Shared\Enums\OrdenCompra\EstadoOCTransferenciaDetalle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrdenCompraTransferenciaDetalle extends Model
{
    protected $table = 'orden_compra_transferencia_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_orden_compra_transferencia', // la transferencia
        'id_orden_compra_recepcion_detalle', // el detalle de la recepcion de la orden de compra
        'id_lote_producto', // el lote del que se esta sacando stock
        'id_activo_fijo', // si se transfiere un activo
        //
        'cantidad_transferida_base', // la cantidad transferida en la unidad de medida base del producto
        //
        'comentario',
        'estado', // En despacho, recepcionado parcialmente, recepcion completa
    ];


    /**
     * Crea uno o varios detalles de recepción de reposición en una sola consulta.
     * @param array $detalles:
     *  {
     *      id_orden_compra_recepcion_detalle: int, 
     *      id_lote_producto: int, 
     *      cantidad_transferida_base: float, 
     *      comentario: string | null,
     *      estado: EstadoOCTransferenciaDetalle
     *  }
     */
    public static function crear_detalle(
        int $id_transferencia,
        array $detalles
    ): bool {
        // Si pasas un solo registro asociativo, lo convertimos en un arreglo de arreglos.
        if (!isset($detalles[0]) || !is_array($detalles[0])) {
            $detalles = [$detalles];
        }

        $insertData = [];

        foreach ($detalles as $detalle) {
            $insertData[] = [
                'id_orden_compra_transferencia' => $id_transferencia,
                'id_orden_compra_recepcion_detalle' => $detalle['id_orden_compra_recepcion_detalle'],
                'id_lote_producto' => $detalle['id_lote_producto'],
                'cantidad_transferida_base' => $detalle['cantidad_transferida_base'],
                'comentario' => $detalle['comentario'],
                'estado' => $detalle['estado']->value ?? EstadoOCTransferenciaDetalle::RecepcionCompleta->value,
            ];
        }

        // Ejecuta todo en una sola consulta a la base de datos
        return self::insert($insertData);
    }

    /**
     * Obtiene un solo registro o todos los detalles de una entrega por prestamo
     */
    public static function get_detalles(
        ?int $id_transferencia_detalle = null,
        null|array|int $ids_transferencias = null
    ) {
        $sql = '
        SELECT
            trnd.id AS id_transferencia_detalle,
            trnd.id_orden_compra_transferencia,
            trnd.id_orden_compra_recepcion_detalle,
            
            --
            prod.id AS id_producto,
            prod.nombre AS producto,
            --
            
            -- el lote tomado para la entrega
            trnd.id_lote_producto,
            lt.correlativo as lote_correlativo,
            --
        
            -- unidad de medida base del producto
            um_bs.id as id_unidad_medida_base, 
            um_bs.abreviatura as unidad_medida_base_abv,
            trnd.cantidad_transferida_base, -- cantidad transferida segun la unidad de medida base del producto
            --
        
            -- unidad de medida del lote de donde salio
            lt.id_unidad_medida as id_unidad_medida_lot,
            um_lt.abreviatura AS unidad_medida_lot_abv,
            lt.contenido_por_presentacion as contenido_por_presentacion_lot, -- cuantas unidades de medida base tiene la unidad del lote
            (trnd.cantidad_transferida_base / lt.contenido_por_presentacion) AS cantidad_transferida_lot, -- cuanto representa lo entregado para el lote
            --
        
            -- unidad de medida de la orden de compra
            um_oc.id as id_unidad_medida_oc,
            um_oc.abreviatura AS unidad_medida_oc_abv,
            ocd.contenido_por_presentacion as contenido_por_presentacion_oc, -- cuantas unidades base hay por una unidad del detalle de la oc
            (trnd.cantidad_transferida_base / ocd.contenido_por_presentacion) as cantidad_transferida_oc, -- cantidad entregada segun la unidad de medida de la oc
        
            --
            trnd.comentario,
            trnd.estado
        FROM
            orden_compra_transferencia_detalle trnd
        
            -- lote del que se saco el stock
        INNER JOIN lote_producto lt on lt.id = trnd.id_lote_producto

        -- info del detalle de la orden de compra
        INNER JOIN orden_compra_recepcion_detalle rcd ON rcd.id = trnd.id_orden_compra_recepcion_detalle
        INNER JOIN orden_compra_detalle ocd on ocd.id = rcd.id_orden_compra_detalle
        
        -- producto
        INNER JOIN producto prod ON prod.id = ocd.id_producto
        
        -- unidad base
        INNER JOIN unidad_medida um_bs ON um_bs.id = prod.id_unidad_medida_base
        
        -- unidad de la orden de compra
		INNER JOIN unidad_medida um_oc ON um_oc.id = ocd.id_unidad_medida
        
        -- unidad del lote
        INNER JOIN unidad_medida um_lt on um_lt.id = lt.id_unidad_medida
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_transferencia_detalle) {
            $sql .= ' AND trnd.id = :id_transferencia_detalle';
            $params['id_transferencia_detalle'] = $id_transferencia_detalle;
            return DB::selectOne($sql, $params);
        }

        if (is_array($ids_transferencias)) {
            $sql .= ' AND trnd.id_orden_compra_transferencia IN (:ids_transferencias)';
            $params['ids_transferencias'] = implode(',', $ids_transferencias);
        } elseif ($ids_transferencias) {
            $sql .= ' AND trnd.id_orden_compra_transferencia = :id_transferencia';
            $params['id_transferencia'] = $ids_transferencias;
        }

        $sql .= ' ORDER BY prod.nombre ASC';

        return DB::select($sql, $params);
    }
}
