<?php

namespace App\Modules\SolicitudesReabastecimiento\Data;

use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\SolicitudReabastecimientoDetalleLog;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalleLog;

class SolicitudesDetalleData
{

    // Obtener el detalle de una solicitud
    public static function get_detalles_solicitud(int $id_solicitud_reabastecimiento)
    {
        return SolicitudReabastecimientoDetalle::get_detalles_solicitud(
            id_solicitud_reabastecimiento: $id_solicitud_reabastecimiento
        );
    }

    // Funcion helpder que ayuda a crear un detalle de solicitud
    public static function crear_detalle_solicitud(
        int $id_solicitud,
        int $id_producto,
        int $id_unidad_medida,
        float $cantidad_solicitada,
        float $contenido_por_presentacion,
        float $cantidad_solicitada_base,
        ?string $comentario,
        bool $con_magnitud = false,
        ?float $cantidad_items = null,
        ?float $valor_magnitud = null,
        ?float $valor_magnitud_base = null,
    ) {
        return SolicitudReabastecimientoDetalle::crear_detalle(
            id_solicitud_reabastecimiento: $id_solicitud,
            id_producto: $id_producto,
            id_unidad_medida: $id_unidad_medida,
            cantidad_solicitada: $cantidad_solicitada,
            contenido_por_presentacion: $contenido_por_presentacion,
            cantidad_solicitada_base: $cantidad_solicitada_base,
            id_requerimiento_almacen_detalle: null,
            comentario: $comentario,
            con_magnitud: $con_magnitud,
            cantidad_items: $cantidad_items,
            valor_magnitud: $valor_magnitud,
            valor_magnitud_base: $valor_magnitud_base,
        );
    }

    // Registrar en trazabilidad el cambio de estado de un detalle de solicitud de reabastecimiento
    public static function insert_detalle_log(
        int $id_solicitud_detalle,
        int $id_empleado,
        string $descripcion,
        EstadoSolicitudDetalleLog $estado
    ) {
        return SolicitudReabastecimientoDetalleLog::crear_log(
            id_solicitud_detalle: $id_solicitud_detalle,
            id_empleado: $id_empleado,
            descripcion: $descripcion,
            estado: $estado
        );
    }

    /**
     * Obtiene la trazabilidad de un detalle de solicitud
     */
    public static function get_trazabilidad_by_detalle(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalleLog::get_logs(
            id_solicitud_detalle: $id_detalle
        );
    }

    /**
     * Devuelve la fila cruda del detalle. Sirve para que `editar_solicitud`
     * valide que el item aun no tenga entregas iniciadas antes de modificarlo
     * o eliminarlo.
     */
    public static function get_detalle_raw(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalle::where('id', $id_detalle)->first();
    }

    /**
     * Actualiza los campos editables de un detalle. Whitelist explicita:
     * id_producto, id_solicitud_reabastecimiento, id_requerimiento_almacen_detalle,
     * id_empleado_atencion, estado, cantidades entregadas y comentario_decision NO
     * se pueden modificar desde aca (los gestiona logistica/operaciones).
     */
    public static function update_detalle_editable(int $id_detalle, array $campos)
    {
        $permitidos = [
            'id_unidad_medida',
            'cantidad_solicitada',
            'contenido_por_presentacion',
            'cantidad_solicitada_base',
            'comentario',
            'con_magnitud',
            'cantidad_items',
            'valor_magnitud',
            'valor_magnitud_base',
        ];

        $updateData = [];
        foreach ($permitidos as $key) {
            if (array_key_exists($key, $campos)) {
                $updateData[$key] = $campos[$key];
            }
        }

        if (empty($updateData)) {
            return 0;
        }

        // Asegurar el tipo correcto para booleanos antes de persistir.
        if (isset($updateData['con_magnitud'])) {
            $updateData['con_magnitud'] = $updateData['con_magnitud'] ? 1 : 0;
        }

        return SolicitudReabastecimientoDetalle::where('id', $id_detalle)
            ->update($updateData);
    }

    /**
     * Elimina un detalle solo si su cantidad_entregada_base es 0 (aun no
     * tuvo despacho). Devuelve true si elimino, false si bloqueo por seguridad.
     * Tambien elimina los logs de trazabilidad asociados para mantener
     * consistencia (no hay FK, manejo por aplicacion).
     */
    public static function delete_detalle_si_no_entregado(int $id_detalle): bool
    {
        $fila = self::get_detalle_raw($id_detalle);
        if (!$fila) {
            return false;
        }
        if ((float) $fila->cantidad_entregada_base > 0) {
            return false;
        }
        SolicitudReabastecimientoDetalleLog::where(
            'id_solicitud_reabastecimiento_detalle',
            $id_detalle
        )->delete();
        $deleted = SolicitudReabastecimientoDetalle::where('id', $id_detalle)->delete();
        return $deleted > 0;
    }

    /**
     * Recalcula `cantidad_solicitada_base` con la misma formula que el
     * registro original.
     */
    public static function recalcular_cantidad_base(
        float $cantidad_solicitada,
        float $contenido_por_presentacion,
        bool $con_magnitud,
        ?float $cantidad_items,
        ?float $valor_magnitud_base
    ): float {
        if ($con_magnitud && $cantidad_items && $valor_magnitud_base) {
            return $cantidad_items * $valor_magnitud_base;
        }
        return $cantidad_solicitada * $contenido_por_presentacion;
    }
}
