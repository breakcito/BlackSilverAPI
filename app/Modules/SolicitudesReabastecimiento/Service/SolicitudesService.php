<?php

namespace App\Modules\SolicitudesReabastecimiento\Service;

use App\Shared\Enums\_Generic\Premura;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalleLog;
use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimiento\Data\SolicitudesData;
use App\Modules\SolicitudesReabastecimiento\Data\SolicitudesDetalleData;
use App\Models\SolicitudReabastecimiento;
use App\Models\SolicitudReabastecimientoDetalle;
use Illuminate\Support\Facades\DB;

class SolicitudesService
{

    /**
     * Obtener todas la lista de solicitudes hechas por el empleado
     */
    public static function get_solicitudes(int $id_empleado, int $mes, int $yearcito)
    {
        $data = SolicitudesData::get_solicitudes(
            id_empleado: $id_empleado,
            mes: $mes,
            yearcito: $yearcito
        );

        return ApiResponse::success($data);
    }

    /**
     * Registrar una solicitud y sus detalles
     */
    public static function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        Premura $premura,
        bool $es_auditable,
        // id_producto, id_unidad_medida, cantidad_solicitada,
        // contenido_por_presentacion
        // comentario
        array $detalles,
        ?string $observacion,
        ?string $fecha_entrega_requerida,
        ?string $fecha_solicitud = null,
    ) {
        // 1. Generar Correlativo
        $correlativoData = SolicitudesData::get_nuevo_correlativo();
        $correlativo = $correlativoData['correlativo'];
        $numero_correlativo = $correlativoData['numero_correlativo'];

        // 2. Crear cabecera
        $id_solicitud = SolicitudesData::crear_solicitud(
            $id_almacen_solicitante,
            $id_empleado_solicitante,
            $correlativo,
            $numero_correlativo,
            $premura,
            $es_auditable,
            $observacion,
            $fecha_entrega_requerida,
            $fecha_solicitud
        );

        // 3. Crear detalles
        foreach ($detalles as $detalle) {
            $id_producto = (int) $detalle['id_producto'];
            $id_unidad_medida = (int) $detalle['id_unidad_medida'];
            $cantidad_solicitada = (float) $detalle['cantidad_solicitada'];
            $contenido_por_presentacion = (float) $detalle['contenido_por_presentacion'];
            $cantidad_solicitada_base = $cantidad_solicitada * $contenido_por_presentacion;
            $comentario = $detalle['comentario'] ?? null;

            // Campos de smart calc: opcionales. Si el front los manda, se
            // persisten tal cual; si no, quedan null/0 (modelo clasico).
            $con_magnitud = (bool) ($detalle['con_magnitud'] ?? false);
            $cantidad_items = isset($detalle['cantidad_items'])
                ? (float) $detalle['cantidad_items']
                : null;
            $valor_magnitud = isset($detalle['valor_magnitud'])
                ? (float) $detalle['valor_magnitud']
                : null;
            $valor_magnitud_base = isset($detalle['valor_magnitud_base'])
                ? (float) $detalle['valor_magnitud_base']
                : null;

            $id_solicitud_detalle = SolicitudesDetalleData::crear_detalle_solicitud(
                $id_solicitud,
                $id_producto,
                $id_unidad_medida,
                $cantidad_solicitada,
                $contenido_por_presentacion,
                $cantidad_solicitada_base,
                $comentario,
                $con_magnitud,
                $cantidad_items,
                $valor_magnitud,
                $valor_magnitud_base,
            );

            $estadoEnum = EstadoSolicitudDetalleLog::EsperandoAprobacion;
            SolicitudesDetalleData::insert_detalle_log(
                (int) $id_solicitud_detalle,
                $id_empleado_solicitante,
                $estadoEnum->getGlosa($comentario),
                $estadoEnum
            );
        }

        return ApiResponse::success(
            SolicitudesData::get_solicitud_by_id($id_solicitud),
            'Solicitud generada correctamente'
        );
    }

    /**
     * Obtener los detalles de una solicitud
     */
    public static function get_detalles_solicitud(int $id_solicitud)
    {
        $detalles = SolicitudesDetalleData::get_detalles_solicitud($id_solicitud);
        return ApiResponse::success($detalles);
    }


    /**
     * Obtener la trazabildiad de un detalle
     */
    public static function get_trazabilidad_by_detalle(int $id_solicitud_detalle)
    {
        $detalles = SolicitudesDetalleData::get_trazabilidad_by_detalle($id_solicitud_detalle);
        return ApiResponse::success($detalles);
    }

    /**
     * Edita una solicitud existente. Mismas reglas que
     * `RequerimientosAlmacenAtencion\AtencionService::editar_requerimiento`:
     *
     * - Cabecera solo si al menos UN detalle aun no tiene entregas iniciadas.
     * - Por cada detalle a editar: bloquear si ese detalle especifico ya
     *   tuvo entregas (`cantidad_entregada_base > 0`).
     * - Eliminar detalle solo si `cantidad_entregada_base == 0`.
     * - Crear nuevos detalles con `EsperandoAprobacion` y log de trazabilidad.
     *
     * Todo dentro de `DB::transaction`.
     */
    public static function editar_solicitud(
        int $id_solicitud,
        int $id_empleado_editor,
        array $cabecera,
        array $detalles_editar,
        array $detalles_eliminar,
        array $detalles_crear = []
    ) {
        return DB::transaction(function () use ($id_solicitud, $id_empleado_editor, $cabecera, $detalles_editar, $detalles_eliminar, $detalles_crear) {
            $solicitud = SolicitudReabastecimiento::find($id_solicitud);
            if (!$solicitud) {
                return ApiResponse::error('Solicitud no encontrada');
            }

            // Validacion global estricta: si CUALQUIER detalle ya tiene despacho,
            // NO se permite editar la solicitud completa (regla todo-o-nada).
            $detallesActuales = SolicitudReabastecimientoDetalle::where(
                'id_solicitud_reabastecimiento',
                $id_solicitud
            )->get();

            $algunoEntregado = $detallesActuales->contains(
                fn($d) => (float) $d->cantidad_entregada_base > 0.0
            );

            if ($algunoEntregado) {
                return ApiResponse::error(
                    'No se puede editar una solicitud que ya tiene entregas iniciadas'
                );
            }

            // 1. Actualizar cabecera (whitelist ya aplicada por Data).
            SolicitudesData::update_solicitud_cabecera($id_solicitud, $cabecera);

            // 2. Procesar detalles a editar.
            foreach ($detalles_editar as $det) {
                $idDetalle = (int) ($det['id_solicitud_reabastecimiento_detalle'] ?? 0);
                if ($idDetalle <= 0) {
                    continue;
                }

                $fila = SolicitudesDetalleData::get_detalle_raw($idDetalle);
                if (!$fila || (int) $fila->id_solicitud_reabastecimiento !== $id_solicitud) {
                    return ApiResponse::error(
                        "El detalle {$idDetalle} no pertenece a esta solicitud"
                    );
                }
                if ((float) $fila->cantidad_entregada_base > 0) {
                    return ApiResponse::error(
                        "El detalle {$idDetalle} ya tiene entregas iniciadas y no puede modificarse"
                    );
                }

                // Tomamos los valores actuales como base; el front envia solo
                // los campos a modificar, asi que mezclamos con la fila real.
                $cantidad = isset($det['cantidad_solicitada'])
                    ? (float) $det['cantidad_solicitada']
                    : (float) $fila->cantidad_solicitada;
                $contenido = isset($det['contenido_por_presentacion'])
                    ? (float) $det['contenido_por_presentacion']
                    : (float) $fila->contenido_por_presentacion;
                $conMagnitud = array_key_exists('con_magnitud', $det)
                    ? (bool) $det['con_magnitud']
                    : (bool) $fila->con_magnitud;
                $cantidadItems = isset($det['cantidad_items'])
                    ? (float) $det['cantidad_items']
                    : (float) ($fila->cantidad_items ?? 0);
                $valorMagnitudBase = isset($det['valor_magnitud_base'])
                    ? (float) $det['valor_magnitud_base']
                    : (float) ($fila->valor_magnitud_base ?? 0);

                $cantidad_base = SolicitudesDetalleData::recalcular_cantidad_base(
                    $cantidad,
                    $contenido,
                    $conMagnitud,
                    $cantidadItems,
                    $valorMagnitudBase
                );

                SolicitudesDetalleData::update_detalle_editable(
                    $idDetalle,
                    [
                        'id_unidad_medida' => isset($det['id_unidad_medida'])
                            ? (int) $det['id_unidad_medida']
                            : (int) $fila->id_unidad_medida,
                        'cantidad_solicitada' => $cantidad,
                        'contenido_por_presentacion' => $contenido,
                        'cantidad_solicitada_base' => $cantidad_base,
                        'comentario' => array_key_exists('comentario', $det)
                            ? ($det['comentario'] ?: null)
                            : $fila->comentario,
                        'con_magnitud' => $conMagnitud,
                        'cantidad_items' => array_key_exists('cantidad_items', $det)
                            ? $det['cantidad_items']
                            : $fila->cantidad_items,
                        'valor_magnitud' => array_key_exists('valor_magnitud', $det)
                            ? $det['valor_magnitud']
                            : $fila->valor_magnitud,
                        'valor_magnitud_base' => array_key_exists('valor_magnitud_base', $det)
                            ? $det['valor_magnitud_base']
                            : $fila->valor_magnitud_base,
                    ]
                );
            }

            // 3. Procesar detalles a eliminar.
            foreach ($detalles_eliminar as $idDetalle) {
                $idDetalleInt = (int) $idDetalle;
                if ($idDetalleInt <= 0) {
                    continue;
                }
                $deleted = SolicitudesDetalleData::delete_detalle_si_no_entregado($idDetalleInt);
                if (!$deleted) {
                    return ApiResponse::error(
                        "El detalle {$idDetalleInt} no puede eliminarse (ya tiene entregas o no existe)"
                    );
                }
            }

            // 4. Crear nuevos detalles (estado inicial: EsperandoAprobacion + log).
            foreach ($detalles_crear as $det) {
                $id_producto = (int) $det['id_producto'];
                $id_unidad_medida = (int) $det['id_unidad_medida'];
                $cantidad_solicitada = (float) $det['cantidad_solicitada'];
                $contenido_por_presentacion = (float) $det['contenido_por_presentacion'];
                $comentario = $det['comentario'] ?? null;

                $con_magnitud = (bool) ($det['con_magnitud'] ?? false);
                $cantidad_items = isset($det['cantidad_items']) ? (float) $det['cantidad_items'] : null;
                $valor_magnitud = isset($det['valor_magnitud']) ? (float) $det['valor_magnitud'] : null;
                $valor_magnitud_base = isset($det['valor_magnitud_base']) ? (float) $det['valor_magnitud_base'] : null;

                $cantidad_solicitada_base = SolicitudesDetalleData::recalcular_cantidad_base(
                    $cantidad_solicitada,
                    $contenido_por_presentacion,
                    $con_magnitud,
                    $cantidad_items,
                    $valor_magnitud_base
                );

                $idNuevo = SolicitudesDetalleData::crear_detalle_solicitud(
                    $id_solicitud,
                    $id_producto,
                    $id_unidad_medida,
                    $cantidad_solicitada,
                    $contenido_por_presentacion,
                    $cantidad_solicitada_base,
                    $comentario,
                    $con_magnitud,
                    $cantidad_items,
                    $valor_magnitud,
                    $valor_magnitud_base
                );

                $estadoEnum = EstadoSolicitudDetalleLog::EsperandoAprobacion;
                SolicitudesDetalleData::insert_detalle_log(
                    (int) $idNuevo,
                    $id_empleado_editor,
                    $estadoEnum->getGlosa($comentario),
                    $estadoEnum
                );
            }

            return ApiResponse::success(
                SolicitudesData::get_solicitud_by_id($id_solicitud),
                'Solicitud actualizada correctamente'
            );
        });
    }
}
