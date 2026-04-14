<?php

namespace App\Modules\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacenDetalle;
use App\Models\PrestamoAlmacenDetalleLog;
use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoDetalle;

class PrestamosDetalleData
{
    /**
     * Obtiene el detalle de los ítems de un préstamo específico.
     */
    public static function get_detalles_prestamo(int $id_prestamo): array
    {
        return PrestamoAlmacenDetalle::get_detalles(id_prestamo: $id_prestamo);
    }

    /**
     * Cambiar el estado de un detalle de préstamo e insertar registro de trazabilidad.
     */
    public static function update_detalle_estado(
        int $id_prestamo_detalle,
        string $nuevo_estado,
    ): void {
        PrestamoAlmacenDetalle::where("id", $id_prestamo_detalle)
            ->update(["estado" => $nuevo_estado]);
    }

    /**
     * Inserta un log de trazabilidad para un ítem de préstamo.
     */
    public static function insert_detalle_log(
        int $id_prestamo_detalle,
        ?int $id_empleado,
        string $estado,
        ?string $comentario = null
    ): void {
        PrestamoAlmacenDetalleLog::insert([
            "id_prestamo_almacen_detalle" => $id_prestamo_detalle,
            "id_empleado" => $id_empleado,
            "estado" => $estado,
            "descripcion" => $comentario,
            "created_at" => now()
        ]);
    }

    /**
     * Obtener el historial de trazabilidad de un ítem de préstamo.
     */
    public static function get_detalle_logs(int $id_prestamo_detalle): array
    {
        return PrestamoAlmacenDetalleLog::get_logs(id_prestamo_detalle: $id_prestamo_detalle);
    }

    /**
     * Verifica si un ítem de préstamo ya fue cubierto al 100% y cambia su estado.
     */
    public static function verificar_y_cerrar_detalle(int $id_prestamo_detalle, ?int $id_empleado = null): void
    {
        $det = PrestamoAlmacenDetalle::find($id_prestamo_detalle);
        if ($det && $det->cantidad_prestada_base >= $det->cantidad_solicitada_base) {
            $nuevoEstado = EstadoPrestamoDetalle::Completado;
            $det->update(['estado' => $nuevoEstado->value]);

            // INSERTAR LOG AUTOMÁTICO DE CIERRE
            self::insert_detalle_log(
                $id_prestamo_detalle,
                $id_empleado,
                $nuevoEstado->value,
                $nuevoEstado->getGlosa()
            );
        }
    }
    /**
     * Obtener un detalle de préstamo por ID.
     */
    public static function get_detalle_by_id(int $id_prestamo_detalle)
    {
        return PrestamoAlmacenDetalle::find($id_prestamo_detalle);
    }
}
