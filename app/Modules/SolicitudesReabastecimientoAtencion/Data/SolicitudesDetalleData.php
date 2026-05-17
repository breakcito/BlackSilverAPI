<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Data;

use App\Models\SolicitudReabastecimiento;
use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\SolicitudReabastecimientoDetalleLog;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalleLog;
use Illuminate\Support\Facades\DB;

class SolicitudesDetalleData
{

    /**
     * Obtiene los detalles de una solicitud de reabastecimiento desde el 
     * punto de vista del area de logistica
     */
    public static function get_detalles_by_solicitud(
        int $id_solicitud
    ) {
        return SolicitudReabastecimientoDetalle::get_detalles_solicitud(
            id_solicitud_reabastecimiento: $id_solicitud,
            con_stock_disponible: true
        );
    }

    /**
     * Obtiene los logs de trazabilidad de un detalle
     */
    public static function get_detalle_logs(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalleLog::get_logs(id_solicitud_detalle: $id_detalle);
    }

    /**
     * Inserta un log de trazabilidad para un detalle
     */
    public static function insert_detalle_log(
        int $id_detalle,
        int $id_empleado,
        string $descripcion,
        EstadoSolicitudDetalleLog $estado
    ) {
        return SolicitudReabastecimientoDetalleLog::crear_log(
            id_solicitud_detalle: $id_detalle,
            id_empleado: $id_empleado,
            descripcion: $descripcion,
            estado: $estado
        );
    }

    /**
     * Actualiza el estado de un detalle de solicitud
     */
    public static function update_detalle_estado(int $id_detalle, string $estado, int $id_empleado, ?string $comentario = null)
    {
        $updateData = [
            'estado' => $estado,
            'id_empleado_atencion' => $id_empleado
        ];

        if ($comentario !== null) {
            $updateData['comentario_decision'] = $comentario;
        }

        return SolicitudReabastecimientoDetalle::where('id', $id_detalle)
            ->update($updateData);
    }


    /**
     * Incrementar cantidades entregadas en el detalle del requerimiento
     */
    public static function increment_detalle_entregado(int $id_detalle, float $cantidad_sol, float $cantidad_base)
    {
        return SolicitudReabastecimientoDetalle::where('id', $id_detalle)
            ->incrementEach([
                'cantidad_entregada' => $cantidad_sol,
                'cantidad_entregada_base' => $cantidad_base
            ]);
    }


    public static function get_id_solicitud_by_detalle(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalle::select('id_solicitud_reabastecimiento')
            ->where('id', $id_detalle)
            ->first();
    }

    /**
     * Obtener detalle por id para procesos de entrega
     */
    public static function get_detalle_by_id(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalle::select(
            'id',
            'id_solicitud_reabastecimiento',
            'id_producto',
            'id_requerimiento_almacen_detalle',
            'cantidad_entregada_base',
            'cantidad_solicitada_base'
        )
            ->where('id', $id_detalle)
            ->first();
    }

    /**
     * Obtener detalle por id simplificado para préstamos
     */
    public static function get_detalle_para_prestamo(int $id_detalle)
    {
        return SolicitudReabastecimientoDetalle::select(
            'id',
            'id_producto',
            'id_unidad_medida',
            'contenido_por_presentacion',
            'cantidad_solicitada',
            'cantidad_entregada'
        )
            ->where('id', $id_detalle)
            ->first();
    }

    /**
     * Obtener el ID del almacén solicitante de una solicitud
     */
    public static function get_almacen_solicitante_id_by_solic_id(int $id_solicitud)
    {
        return SolicitudReabastecimiento::where('id', $id_solicitud)
            ->value('id_almacen_solicitante');
    }

    /**
     * Inserta un log de trazabilidad con estado como string (para procesos externos)
     */
    public static function insert_log_simple(int $id_detalle, int $id_empleado, string $estado, string $descripcion)
    {
        return SolicitudReabastecimientoDetalleLog::insert([
            'id_solicitud_reabastecimiento_detalle' => $id_detalle,
            'id_empleado' => $id_empleado,
            'estado' => $estado,
            'descripcion' => $descripcion,
            'created_at' => now(),
        ]);
    }
}
