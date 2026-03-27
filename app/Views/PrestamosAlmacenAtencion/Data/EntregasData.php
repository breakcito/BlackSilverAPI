<?php

namespace App\Views\PrestamosAlmacenAtencion\Data;

use App\Models\PrestamoAlmacenEntrega;
use App\Models\PrestamoAlmacenEntregaDetalle;
use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\SolicitudReabastecimientoDetalleLog;
use App\Shared\Enums\PrestamoAlmacen\EstadoEntregaPrestamo;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Enums\Periodo;
use Illuminate\Support\Facades\DB;

class EntregasData
{
    /**
     * Genera el correlativo para una nueva entrega de préstamo.
     * Se filtra por almacén prestamista para tener una secuencia independiente por almacén (ENT-XXXXX).
     */
    public static function get_nuevo_correlativo(int $id_almacen_prestamista): array
    {
        return CorrelativoHelper::generar(
            tabla: 'prestamo_almacen_entrega',
            prefijo: 'ENT',
            filtros: ['pa.id_almacen_prestamista' => $id_almacen_prestamista],
            longitudCeros: 5,
            reseteo: Periodo::Anual,
            columnaFecha: 'created_at',
            queryModifier: function ($query) {
                $query->join('prestamo_almacen as pa', 'pa.id', '=', 'pae.id_prestamo_almacen');
            },
            alias: 'pae'
        );
    }

    /**
     * Crea la cabecera de una entrega de préstamo.
     */
    public static function crear_entrega(
        int $id_prestamo_almacen,
        int $id_empleado_entrega,
        int $id_empleado_recibe,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_entrega,
        ?string $observacion,
        ?array $evidencias = null
    ): int {
        return PrestamoAlmacenEntrega::insertGetId([
            'id_prestamo_almacen'   => $id_prestamo_almacen,
            'id_empleado_entrega'   => $id_empleado_entrega,
            'id_empleado_recibe'    => $id_empleado_recibe,
            'correlativo'           => $correlativo,
            'numero_correlativo'    => $numero_correlativo,
            'fecha_hora_entrega'    => $fecha_hora_entrega,
            'observacion'           => $observacion,
            'evidencias'            => $evidencias ? json_encode($evidencias) : null,
            'created_at'            => now(),
            'estado'                => EstadoEntregaPrestamo::EnDespacho->value,
        ]);
    }

    /**
     * Obtiene el historial de entregas de un préstamo con sus detalles.
     */
    public static function get_entregas_por_prestamo(int $id_prestamo): array
    {
        return DB::select('
            SELECT
                pae.id AS id_entrega,
                pae.correlativo,
                pae.numero_correlativo,
                pae.fecha_hora_entrega,
                pae.observacion,
                pae.evidencias,
                pae.created_at,
                pae.estado,
                CONCAT(emp_ent.nombre, " ", emp_ent.apellido) AS empleado_entrega,
                CONCAT(emp_rec.nombre, " ", emp_rec.apellido) AS empleado_recibe
            FROM
                prestamo_almacen_entrega pae
            INNER JOIN empleado emp_ent ON emp_ent.id = pae.id_empleado_entrega
            INNER JOIN empleado emp_rec ON emp_rec.id = pae.id_empleado_recibe
            WHERE pae.id_prestamo_almacen = :id_prestamo
            ORDER BY pae.created_at DESC
        ', ['id_prestamo' => $id_prestamo]);
    }

    public static function registrar_incremento_cantidades_prestadas(int $id_prestamo_detalle, float $cant_sol, float $cant_base): void
    {
        DB::table("prestamo_almacen_detalle")
            ->where("id", $id_prestamo_detalle)
            ->incrementEach([
                "cantidad_prestada" => $cant_sol,
                "cantidad_prestada_base" => $cant_base
            ]);
    }

    /**
     * Actualiza la cantidad entregada en la solicitud de reabastecimiento vinculada.
     */
    public static function incrementar_entregado_reabastecimiento(int $id_solicitud_detalle, float $cantidad_sol, float $cantidad_base): void
    {
        SolicitudReabastecimientoDetalle::where('id', $id_solicitud_detalle)
            ->incrementEach([
                'cantidad_entregada' => $cantidad_sol,
                'cantidad_entregada_base' => $cantidad_base
            ]);
    }

    /**
     * Obtiene los IDs vinculados para saber a qué solicitud afecta.
     */
    public static function get_ids_vinculados_by_prestamo_detalle(int $id_prestamo_detalle)
    {
        return DB::table('prestamo_almacen_detalle')
            ->select('id_solicitud_reabastecimiento_detalle')
            ->where('id', $id_prestamo_detalle)
            ->first();
    }

    /**
     * Inserta un log en la solicitud de reabastecimiento vinculada.
     */
    public static function insertar_log_reabastecimiento(int $id_solicitud_detalle, int $id_empleado, string $descripcion, string $estado): void
    {
        SolicitudReabastecimientoDetalleLog::insert([
            'id_solicitud_reabastecimiento_detalle' => $id_solicitud_detalle,
            'id_empleado' => $id_empleado,
            'descripcion' => $descripcion,
            'estado' => $estado,
            'created_at' => now()
        ]);
    }

    /**
     * Obtiene el historial de entregas de todos los préstamos vinculados a una solicitud de reabastecimiento.
     */
    public static function get_entregas_por_solicitud(int $id_solicitud): array
    {
        return DB::select('
            SELECT
                pae.id AS id_entrega,
                pae.correlativo,
                pae.numero_correlativo,
                pae.fecha_hora_entrega,
                pae.observacion,
                pae.evidencias,
                pae.created_at,
                pae.estado,
                CONCAT(emp_ent.nombre, " ", emp_ent.apellido) AS empleado_entrega,
                CONCAT(emp_rec.nombre, " ", emp_rec.apellido) AS empleado_recibe,
                alm.nombre AS almacen_entrega,
                pa.correlativo AS correlativo_prestamo,
                pa.id AS id_prestamo,
                \'Prestamo\' AS tipo_entrega
            FROM
                prestamo_almacen_entrega pae
            INNER JOIN prestamo_almacen pa ON pa.id = pae.id_prestamo_almacen
            INNER JOIN almacen alm ON alm.id = pa.id_almacen_prestamista
            INNER JOIN empleado emp_ent ON emp_ent.id = pae.id_empleado_entrega
            INNER JOIN empleado emp_rec ON emp_rec.id = pae.id_empleado_recibe
            WHERE pa.id_solicitud_reabastecimiento = :id_solicitud
            ORDER BY pae.created_at DESC
        ', ['id_solicitud' => $id_solicitud]);
    }
}
