<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\PrestamoAlmacenReposicion;
use App\Models\PrestamoAlmacenReposicionDetalle;
use App\Models\PrestamoAlmacen;
use App\Models\PrestamoAlmacenDetalle;
use App\Models\PrestamoAlmacenDetalleLog;
use App\Shared\Enums\PrestamoAlmacen\EstadoDetallePrestamo;
use App\Shared\Enums\PrestamoAlmacen\EstadoDetalleReposicion;
use App\Shared\Enums\PrestamoAlmacen\EstadoReposicion;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class ReposicionesData
{

    /**
     * Genera un nuevo correlativo para una reposición.
     */
    public static function get_nuevo_correlativo(int $id_almacen)
    {
        return CorrelativoHelper::generar(
            tabla: 'prestamo_almacen_reposicion',
            prefijo: 'RPS',
            filtros: ['id_almacen_entrega' => $id_almacen],
            columnaFecha: 'fecha_hora_reposicion'
        );
    }

    /**
     * Obtiene el historial de reposiciones de un préstamo.
     */
    public static function get_historial_reposiciones(int $id_prestamo_almacen): array
    {
        $sql = '
        SELECT 
            r.id as id_reposicion,
            r.correlativo,
            r.fecha_hora_reposicion,
            r.created_at,
            r.estado,
            r.observacion,
            r.evidencias,
            a.nombre AS almacen_entrega,
            CONCAT(e.nombre, " ", e.apellido) AS registrado_por
        FROM 
            prestamo_almacen_reposicion r
        INNER JOIN almacen a ON a.id = r.id_almacen_entrega
        INNER JOIN empleado e ON e.id = r.id_empleado_registro
        WHERE 
            r.id_prestamo_almacen = :id_prestamo_almacen
        ORDER BY 
            r.created_at DESC
        ';
        return DB::select($sql, ['id_prestamo_almacen' => $id_prestamo_almacen]);
    }

    /**
     * Obtiene los detalles de una reposición.
     */
    public static function get_detalles_reposicion(int $id_reposicion): array
    {
        return DB::select("
            SELECT 
                rd.id,
                rd.cantidad_base,
                rd.cantidad_lote,
                rd.cantidad_solicitud,
                rd.estado,
                p.nombre AS producto,
                um.abreviatura AS unidad_medida_base,
                lp.correlativo AS lote_correlativo
            FROM 
                prestamo_almacen_reposicion_detalle rd
            INNER JOIN prestamo_almacen_detalle pd ON pd.id = rd.id_prestamo_almacen_detalle
            INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = pd.id_solicitud_reabastecimiento_detalle
            INNER JOIN producto p ON p.id = srd.id_producto
            INNER JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
            INNER JOIN lote_producto lp ON lp.id = rd.id_lote_producto
            WHERE 
                rd.id_prestamo_almacen_reposicion = :id_reposicion
            ORDER BY p.nombre ASC
        ", ['id_reposicion' => $id_reposicion]);
    }

    /**
     * Obtener cabecera de préstamo por ID
     */
    public static function get_prestamo_by_id(int $id)
    {
        return PrestamoAlmacen::where('id', $id)->first();
    }

    /**
     * Obtener detalle de préstamo por ID
     */
    public static function get_detalle_prestamo_by_id(int $id)
    {
        return PrestamoAlmacenDetalle::where('id', $id)->first();
    }

    /**
     * Incrementar cantidades repuestas en el detalle de préstamo
     */
    public static function increment_cantidad_repuesta(int $id_detalle, float $cant_sol, float $cant_base)
    {
        return PrestamoAlmacenDetalle::where('id', $id_detalle)
            ->update([
                'cantidad_repuesta' => DB::raw("cantidad_repuesta + $cant_sol"),
                'cantidad_repuesta_base' => DB::raw("cantidad_repuesta_base + $cant_base"),
            ]);
    }

    /**
     * Insertar log de trazabilidad en el detalle de préstamo
     */
    public static function insert_detalle_log(int $id_detalle, int $id_empleado, string $glosa)
    {
        return PrestamoAlmacenDetalleLog::insert([
            'id_prestamo_almacen_detalle' => $id_detalle,
            'id_empleado' => $id_empleado,
            'estado' => EstadoDetallePrestamo::EnReposicion->value,
            'descripcion' => $glosa,
            'created_at' => now(),
        ]);
    }

    /**
     * Inserta una nueva reposición.
     */
    public static function insert_reposicion(
        int $id_prestamo_almacen,
        int $id_almacen_entrega,
        int $id_empleado_registro,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_reposicion,
        ?string $observacion = null,
        ?string $evidencias = null
    ): int {
        return PrestamoAlmacenReposicion::insertGetId([
            'id_prestamo_almacen' => $id_prestamo_almacen,
            'id_almacen_entrega' => $id_almacen_entrega,
            'id_empleado_registro' => $id_empleado_registro,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'fecha_hora_reposicion' => $fecha_hora_reposicion,
            'observacion' => $observacion,
            'evidencias' => $evidencias,
            'estado' => EstadoReposicion::EnDespacho->value,
            'created_at' => now(),
        ]);
    }

    /**
     * Inserta un detalle de reposición.
     */
    public static function insert_detalle_reposicion(
        int $id_reposicion,
        int $id_prestamo_detalle,
        int $id_lote_producto,
        float $cantidad_base,
        float $cantidad_lote,
        float $cantidad_solicitud,
    ): bool {
        return PrestamoAlmacenReposicionDetalle::insert([
            'id_prestamo_almacen_reposicion' => $id_reposicion,
            'id_prestamo_almacen_detalle' => $id_prestamo_detalle,
            'id_lote_producto' => $id_lote_producto,
            'cantidad_base' => $cantidad_base,
            'cantidad_lote' => $cantidad_lote,
            'cantidad_solicitud' => $cantidad_solicitud,
            'estado' => EstadoDetalleReposicion::EnDespacho->value,
        ]);
    }
}
