<?php

namespace App\Modules\Dashboard\Data;

use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitud;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use Illuminate\Support\Facades\DB;

/**
 * Indicador 8: pendientes de atencion (embudo logistico).
 *
 * Cuenta los ITEMS que esperan accion, no las cabeceras: lo que truly traba
 * el flujo es el item. Se reunen requerimientos de almacen y solicitudes de
 * reabastecimiento, que comparten la misma cadena de aprobacion y despacho.
 *
 * Los estados terminales se excluyen del conteo: un item rechazado, cerrado o
 * completado ya no espera a nadie.
 *
 * El conteo trae el almacen de cada etapa porque el dashboard filtra por
 * almacen en el navegador: el filtro tiene que poder recortar el embudo, no
 * solo la lista de vencidos.
 */
class PendientesAtencionData
{
    /**
     * Conteo de items por tipo de documento, etapa y almacen.
     */
    public static function get_conteo_por_etapa(): array
    {
        $requerimiento_en_flujo = self::sql_lista([
            EstadoRequerimiento::Generado->value,
            EstadoRequerimiento::EnDespacho->value,
        ]);

        $solicitud_en_flujo = self::sql_lista([
            EstadoSolicitud::Generada->value,
            EstadoSolicitud::EnDespacho->value,
        ]);

        $terminales = self::sql_lista([
            EstadoRequerimientoDetalle::Rechazado->value,
            EstadoRequerimientoDetalle::RechazadoLogistica->value,
            EstadoRequerimientoDetalle::Cerrado->value,
            EstadoRequerimientoDetalle::Completado->value,
        ]);

        $sql = "
        SELECT 'REQUERIMIENTO' AS tipo, d.estado AS etapa,
               r.id_almacen_destino AS id_almacen, a.nombre AS almacen,
               COUNT(*) AS cantidad
        FROM requerimiento_almacen_detalle d
        INNER JOIN requerimiento_almacen r ON r.id = d.id_requerimiento_almacen
        LEFT JOIN almacen a ON a.id = r.id_almacen_destino
        WHERE r.estado IN ($requerimiento_en_flujo)
          AND d.estado NOT IN ($terminales)
        GROUP BY d.estado, r.id_almacen_destino, a.nombre

        UNION ALL

        SELECT 'SOLICITUD' AS tipo, d.estado AS etapa,
               s.id_almacen_solicitante AS id_almacen, a.nombre AS almacen,
               COUNT(*) AS cantidad
        FROM solicitud_reabastecimiento_detalle d
        INNER JOIN solicitud_reabastecimiento s ON s.id = d.id_solicitud_reabastecimiento
        LEFT JOIN almacen a ON a.id = s.id_almacen_solicitante
        WHERE s.estado IN ($solicitud_en_flujo)
          AND d.estado NOT IN ($terminales)
        GROUP BY d.estado, s.id_almacen_solicitante, a.nombre
        ";

        $rows = DB::select($sql);

        return array_map(fn (object $row) => [
            'tipo' => (string) $row->tipo,
            'etapa' => (string) $row->etapa,
            'id_almacen' => $row->id_almacen !== null ? (int) $row->id_almacen : null,
            'almacen' => $row->almacen !== null ? (string) $row->almacen : null,
            'cantidad' => (int) $row->cantidad,
        ], $rows);
    }

    /**
     * Items cuyo plazo de entrega ya se paso y siguen sin despacho.
     */
    public static function get_vencidos(int $limite = 100): array
    {
        $requerimiento_en_flujo = self::sql_lista([
            EstadoRequerimiento::Generado->value,
            EstadoRequerimiento::EnDespacho->value,
        ]);

        $solicitud_en_flujo = self::sql_lista([
            EstadoSolicitud::Generada->value,
            EstadoSolicitud::EnDespacho->value,
        ]);

        $terminales = self::sql_lista([
            EstadoRequerimientoDetalle::Rechazado->value,
            EstadoRequerimientoDetalle::RechazadoLogistica->value,
            EstadoRequerimientoDetalle::Cerrado->value,
            EstadoRequerimientoDetalle::Completado->value,
        ]);

        $sql = "
        SELECT 'REQUERIMIENTO' AS tipo, r.correlativo, r.fecha_entrega_requerida,
               d.estado AS etapa, p.nombre AS producto, p.es_auditable,
               d.cantidad_solicitada_base,
               r.premura, r.id_almacen_destino AS id_almacen, a.nombre AS almacen
        FROM requerimiento_almacen_detalle d
        INNER JOIN requerimiento_almacen r ON r.id = d.id_requerimiento_almacen
        INNER JOIN producto p ON p.id = d.id_producto
        LEFT JOIN almacen a ON a.id = r.id_almacen_destino
        WHERE r.estado IN ($requerimiento_en_flujo)
          AND d.estado NOT IN ($terminales)
          AND r.fecha_entrega_requerida IS NOT NULL
          AND DATE(r.fecha_entrega_requerida) < CURRENT_DATE

        UNION ALL

        SELECT 'SOLICITUD' AS tipo, s.correlativo, s.fecha_entrega_requerida,
               d.estado AS etapa, p.nombre AS producto, p.es_auditable,
               d.cantidad_solicitada_base,
               s.premura, s.id_almacen_solicitante AS id_almacen, a.nombre AS almacen
        FROM solicitud_reabastecimiento_detalle d
        INNER JOIN solicitud_reabastecimiento s ON s.id = d.id_solicitud_reabastecimiento
        INNER JOIN producto p ON p.id = d.id_producto
        LEFT JOIN almacen a ON a.id = s.id_almacen_solicitante
        WHERE s.estado IN ($solicitud_en_flujo)
          AND d.estado NOT IN ($terminales)
          AND s.fecha_entrega_requerida IS NOT NULL
          AND DATE(s.fecha_entrega_requerida) < CURRENT_DATE

        ORDER BY fecha_entrega_requerida ASC
        LIMIT " . (int) $limite;

        $rows = DB::select($sql);

        return array_map(fn (object $row) => [
            'tipo' => (string) $row->tipo,
            'correlativo' => $row->correlativo !== null ? (string) $row->correlativo : null,
            'producto' => (string) $row->producto,
            'es_auditable' => (bool) $row->es_auditable,
            'etapa' => (string) $row->etapa,
            'cantidad_solicitada_base' => (float) $row->cantidad_solicitada_base,
            'fecha_entrega_requerida' => (string) $row->fecha_entrega_requerida,
            'dias_vencido' => (int) (strtotime('today') - strtotime((string) $row->fecha_entrega_requerida)) / 86400,
            'premura' => $row->premura !== null ? (string) $row->premura : null,
            'id_almacen' => $row->id_almacen !== null ? (int) $row->id_almacen : null,
            'almacen' => $row->almacen !== null ? (string) $row->almacen : null,
        ], $rows);
    }

    /**
     * Construye una lista de literales SQL a partir de valores de enum.
     *
     * Los valores vienen de los Backed Enums del proyecto (no de entrada
     * del usuario), asi que se pueden interpolar de forma segura. El escape
     * de comillas simples se mantiene como defensa.
     *
     * @param array<int, string> $valores
     */
    private static function sql_lista(array $valores): string
    {
        return "'" . implode("','", array_map(
            fn (string $valor) => str_replace("'", "''", $valor),
            $valores
        )) . "'";
    }
}
