<?php

namespace App\Modules\Dashboard\Data;

use App\Shared\Enums\OrdenCompra\EstadoOCTransferencia;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompra;
use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraDetalle;
use Illuminate\Support\Facades\DB;

/**
 * Indicador 4: compras pendientes de recepcion.
 *
 * Una compra esta pendiente cuando su cabecera sigue en "Generada" o "En
 * Recepcion". El capital comprometido es el total de la orden (con IGV) y se
 * acumula POR MONEDA: soles y dolares nunca se suman.
 *
 * Se cruzan tres senales de riesgo:
 * - antiguedad de la orden (compras formalizadas que nunca llegaron),
 * - vencimiento del pago sobre una orden que aun no se recibio,
 * - recepciones con incidencia y transferencias que siguen en transito.
 */
class ComprasPendientesData
{
    /**
     * Ordenes de compra sin recepcion completa.
     *
     * @param int|null $id_almacen Limita a las OC que recepcionan en ese almacen.
     */
    public static function get_ocs_pendientes(?int $id_almacen = null): array
    {
        $sql = '
        SELECT
            oc.id AS id_orden_compra,
            oc.correlativo,
            oc.numero_correlativo,
            oc.fecha_hora_orden,
            oc.fecha_vencimiento_pago,
            oc.moneda,
            oc.total_despues_igv,
            oc.estado,
            prv.razon_social AS proveedor,
            DATEDIFF(CURRENT_DATE, DATE(oc.fecha_hora_orden)) AS dias_antiguedad,
            (
                SELECT COUNT(*)
                FROM orden_compra_detalle d
                WHERE d.id_orden_compra = oc.id
            ) AS items_total,
            (
                SELECT COUNT(*)
                FROM orden_compra_detalle d
                WHERE d.id_orden_compra = oc.id
                  AND d.estado <> :estado_recepcion_completa
            ) AS items_pendientes
        FROM orden_compra oc
        LEFT JOIN proveedor prv ON prv.id = oc.id_proveedor
        WHERE oc.estado IN (:estado_generada, :estado_en_recepcion)
        ';

        $params = [
            'estado_generada' => EstadoOrdenCompra::Generada->value,
            'estado_en_recepcion' => EstadoOrdenCompra::EnRecepcion->value,
            'estado_recepcion_completa' => EstadoOrdenCompraDetalle::RecepcionCompleta->value,
        ];

        if ($id_almacen !== null) {
            $sql .= '
              AND EXISTS (
                SELECT 1
                FROM orden_compra_detalle d
                WHERE d.id_orden_compra = oc.id
                  AND d.id_almacen_recepcionista = :id_almacen
              )
            ';
            $params['id_almacen'] = $id_almacen;
        }

        $sql .= ' ORDER BY oc.fecha_hora_orden ASC';

        $rows = DB::select($sql, $params);

        return array_map([self::class, 'normalizar_oc'], $rows);
    }

    /**
     * Items de las ordenes pendientes, con el estado de cada item.
     *
     * Se listan TODOS los items de la orden (no solo los pendientes) para que
     * el frente pueda mostrar el avance real de cada linea.
     *
     * @param int|null $id_almacen Limita a los items que recepcionan en ese almacen.
     */
    public static function get_items_pendientes(?int $id_almacen = null): array
    {
        $sql = '
        SELECT
            d.id AS id_detalle,
            d.id_orden_compra,
            d.id_producto,
            p.nombre AS producto,
            p.es_auditable,
            d.cantidad_requerida,
            d.cantidad_requerida_base,
            d.contenido_por_presentacion,
            d.precio_unitario_base,
            d.estado,
            d.id_almacen_recepcionista,
            a.nombre AS almacen,
            um.abreviatura AS unidad
        FROM orden_compra_detalle d
        INNER JOIN producto p ON p.id = d.id_producto
        LEFT JOIN almacen a ON a.id = d.id_almacen_recepcionista
        LEFT JOIN unidad_medida um ON um.id = d.id_unidad_medida
        WHERE d.id_orden_compra IN (
            SELECT oc.id
            FROM orden_compra oc
            WHERE oc.estado IN (:estado_generada, :estado_en_recepcion)
        )
        ';

        $params = [
            'estado_generada' => EstadoOrdenCompra::Generada->value,
            'estado_en_recepcion' => EstadoOrdenCompra::EnRecepcion->value,
        ];

        if ($id_almacen !== null) {
            $sql .= ' AND d.id_almacen_recepcionista = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        $sql .= ' ORDER BY d.id_orden_compra ASC, d.id ASC';

        $rows = DB::select($sql, $params);

        return array_map([self::class, 'normalizar_item'], $rows);
    }

    /**
     * Transferencias en transito y recepciones con incidencia.
     *
     * Van en una sola consulta porque comparten el mismo contexto de
     * almacen: contra una base de datos remota cada consulta de mas se
     * paga en tiempo de espera.
     */
    public static function get_senales_transito(?int $id_almacen = null): array
    {
        $sql = '
        SELECT
            (
                SELECT COUNT(*)
                FROM orden_compra_transferencia t
                WHERE t.estado = :estado_en_despacho
            ) AS transferencias_en_transito,
            (
                SELECT COUNT(*)
                FROM orden_compra_recepcion r
                WHERE r.con_incidencia = 1
            ) AS recepciones_con_incidencia
        ';

        $params = ['estado_en_despacho' => EstadoOCTransferencia::EnDespacho->value];

        $row = DB::selectOne($sql, $params);

        $transferencias = (int) ($row->transferencias_en_transito ?? 0);
        $recepciones = (int) ($row->recepciones_con_incidencia ?? 0);

        if ($id_almacen !== null) {
            $row = DB::selectOne(
                '
                SELECT
                    (
                        SELECT COUNT(*)
                        FROM orden_compra_transferencia t
                        WHERE t.estado = :estado_en_despacho
                          AND t.id_almacen_destino = :id_almacen
                    ) AS transferencias_en_transito,
                    (
                        SELECT COUNT(*)
                        FROM orden_compra_recepcion r
                        WHERE r.con_incidencia = 1
                          AND r.id_almacen_recepcionista = :id_almacen_2
                    ) AS recepciones_con_incidencia
                ',
                [
                    'estado_en_despacho' => EstadoOCTransferencia::EnDespacho->value,
                    'id_almacen' => $id_almacen,
                    'id_almacen_2' => $id_almacen,
                ]
            );

            $transferencias = (int) ($row->transferencias_en_transito ?? 0);
            $recepciones = (int) ($row->recepciones_con_incidencia ?? 0);
        }

        return [
            'transferencias_en_transito' => $transferencias,
            'recepciones_con_incidencia' => $recepciones,
        ];
    }

    /**
     * Normaliza una fila de orden de compra.
     */
    private static function normalizar_oc(object $row): array
    {
        $vencimiento = $row->fecha_vencimiento_pago !== null
            ? (string) $row->fecha_vencimiento_pago
            : null;

        return [
            'id_orden_compra' => (int) $row->id_orden_compra,
            'correlativo' => $row->correlativo !== null ? (string) $row->correlativo : null,
            'numero_correlativo' => $row->numero_correlativo !== null ? (string) $row->numero_correlativo : null,
            'proveedor' => $row->proveedor !== null ? (string) $row->proveedor : null,
            'fecha_hora_orden' => (string) $row->fecha_hora_orden,
            'fecha_vencimiento_pago' => $vencimiento,
            'moneda' => (string) $row->moneda,
            'total' => round((float) $row->total_despues_igv, 2),
            'estado' => (string) $row->estado,
            'dias_antiguedad' => (int) $row->dias_antiguedad,
            'items_total' => (int) $row->items_total,
            'items_pendientes' => (int) $row->items_pendientes,
            'pago_vencido' => $vencimiento !== null && $vencimiento < date('Y-m-d'),
        ];
    }

    /**
     * Normaliza una fila de item de orden de compra.
     */
    private static function normalizar_item(object $row): array
    {
        return [
            'id_detalle' => (int) $row->id_detalle,
            'id_orden_compra' => (int) $row->id_orden_compra,
            'id_producto' => (int) $row->id_producto,
            'producto' => (string) $row->producto,
            'es_auditable' => (bool) $row->es_auditable,
            'cantidad_requerida' => (float) $row->cantidad_requerida,
            'cantidad_requerida_base' => (float) $row->cantidad_requerida_base,
            'contenido_por_presentacion' => (float) $row->contenido_por_presentacion,
            'precio_unitario_base' => round((float) $row->precio_unitario_base, 2),
            'estado' => (string) $row->estado,
            'id_almacen_recepcionista' => $row->id_almacen_recepcionista !== null ? (int) $row->id_almacen_recepcionista : null,
            'almacen' => $row->almacen !== null ? (string) $row->almacen : null,
            'unidad' => $row->unidad !== null ? (string) $row->unidad : null,
        ];
    }
}
