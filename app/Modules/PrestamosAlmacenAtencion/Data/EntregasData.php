<?php

namespace App\Modules\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacenDetalle;
use App\Models\PrestamoAlmacenEntrega;
use App\Models\PrestamoAlmacenEntregaDetalle;
use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\SolicitudReabastecimientoDetalleLog;
use App\Shared\Enums\PrestamoAlmacen\EstadoPrestamoEntrega;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalleLog;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class EntregasData
{
    /**
     * Obtener el historial de entregas de un préstamo
     */
    public static function get_entregas_by_prestamo(int $id_prestamo)
    {
        return PrestamoAlmacenEntrega::get_entregas(id_prestamo: $id_prestamo);
    }

    /**
     * Obtener los detalles de una entrega específica
     */
    public static function get_detalles_entrega(int $id_entrega)
    {
        return PrestamoAlmacenEntregaDetalle::get_detalles(id_entrega: $id_entrega);
    }

    /**
     * Obtener el nuevo correlativo para una entrega de préstamo
     * Filtrado por el almacén que presta (id_almacen_prestamista)
     */
    public static function get_nuevo_correlativo()
    {
        return CorrelativoHelper::generar(
            tabla: 'prestamo_almacen_entrega',
            prefijo: 'ENT',
            // filtros: ['pa.id_almacen_prestamista' => $id_almacen_prestamista],
            // queryModifier: function ($q) {
            //     $q->join('prestamo_almacen as pa', 'pa.id', '=', 'pae.id_prestamo_almacen');
            // },
            // alias: 'pae'
        );
    }

    /**
     * Crea la cabecera de una entrega de préstamo
     */
    public static function crear_entrega(
        int $id_prestamo,
        int $id_empleado_entrega,
        int $id_personal_recibe,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_entrega,
        ?string $observacion,
        ?array $evidencias
    ): int {
        return PrestamoAlmacenEntrega::insertGetId([
            'id_prestamo_almacen' => $id_prestamo,
            'id_empleado_entrega' => $id_empleado_entrega,
            'id_personal_recibe'  => $id_personal_recibe,
            'correlativo'         => $correlativo,
            'numero_correlativo'  => $numero_correlativo,
            'fecha_hora_entrega'  => $fecha_hora_entrega,
            'observacion'         => $observacion,
            'evidencias'          => $evidencias ? json_encode($evidencias) : null,
            'created_at'          => now(),
            'estado'              => EstadoPrestamoEntrega::EnDespacho->value
        ]);
    }

    /**
     * Registra el incremento de cantidades entregadas en el detalle del préstamo
     */
    public static function registrar_incremento_cantidades_prestadas(
        int $id_prestamo_detalle,
        float $cantidad,
        float $cantidad_base
    ): void {
        PrestamoAlmacenDetalle::where('id', $id_prestamo_detalle)->increment('cantidad_prestada', $cantidad);
        PrestamoAlmacenDetalle::where('id', $id_prestamo_detalle)->increment('cantidad_prestada_base', $cantidad_base);
    }

    /**
     * Obtiene los IDs vinculados (solicitud reabastecimiento) desde el detalle del préstamo
     */
    public static function get_ids_vinculados_by_prestamo_detalle(int $id_prestamo_detalle)
    {
        return DB::table('prestamo_almacen_detalle')
            ->select('id_solicitud_reabastecimiento_detalle')
            ->where('id', $id_prestamo_detalle)
            ->first();
    }

    /**
     * Incrementa la cantidad entregada en el detalle de la solicitud de reabastecimiento vinculada
     */
    public static function incrementar_entregado_reabastecimiento(
        int $id_solicitud_detalle,
        float $cantidad,
        float $cantidad_base
    ): void {
        SolicitudReabastecimientoDetalle::where('id', $id_solicitud_detalle)->increment('cantidad_entregada', $cantidad);
        SolicitudReabastecimientoDetalle::where('id', $id_solicitud_detalle)->increment('cantidad_entregada_base', $cantidad_base);
    }

    /**
     * Inserta un log en la trazabilidad de la solicitud de reabastecimiento
     */
    public static function insertar_log_reabastecimiento(
        int $id_solicitud_detalle,
        int $id_empleado,
        string $glosa,
        string $estado
    ): void {
        SolicitudReabastecimientoDetalleLog::crear_log(
            $id_solicitud_detalle,
            $id_empleado,
            $glosa,
            EstadoSolicitudDetalleLog::from($estado)
        );
    }

    /**
     * Obtiene el historial de entregas de todos los préstamos vinculados a una solicitud específica
     */
    public static function get_entregas_por_solicitud(int $id_solicitud)
    {
        return PrestamoAlmacenEntrega::get_entregas(id_solicitud_reabastecimiento: $id_solicitud);
    }
}
