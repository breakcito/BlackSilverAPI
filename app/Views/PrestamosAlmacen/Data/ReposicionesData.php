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
use Illuminate\Support\Facades\DB;

class ReposicionesData
{

    /**
     * Genera un nuevo correlativo para una reposición.
     */
    public static function get_nuevo_correlativo(int $id_almacen)
    {
        return PrestamoAlmacenReposicion::get_nuevo_correlativo($id_almacen);
    }

    /**
     * Obtiene el historial de reposiciones de un préstamo.
     */
    public static function get_reposiciones_by_prestamo(int $id_prestamo_almacen): array
    {
        return PrestamoAlmacenReposicion::get_reposiciones(id_prestamo_almacen: $id_prestamo_almacen);
    }

    /**
     * Obtiene los detalles de una reposición.
     */
    public static function get_detalles_reposicion(int $id_reposicion): array
    {
        return DB::select("
            SELECT 
                rd.id as id_reposicion_detalle,
                rd.id_prestamo_almacen_detalle,
                p.id as id_producto,
                p.nombre AS producto,
                rd.id_lote_producto, -- lote de salida
                lt.correlativo AS lote_correlativo,
                -- unidad de medida del producto
                p.id_unidad_medida_base,
                um_bs.nombre as unidad_medida_base,
                um_bs.abreviatura AS unidad_medida_base_abv,
                -- unidad de medida del prestamo
                --
                rd.cantidad_base,
                rd.cantidad_lote,
                rd.cantidad_solicitud,
                --
                rd.estado
            FROM 
                prestamo_almacen_reposicion_detalle rd
            INNER JOIN prestamo_almacen_detalle pd ON pd.id = rd.id_prestamo_almacen_detalle
            INNER JOIN producto p ON p.id = pd.id_producto
            INNER JOIN unidad_medida um_bs ON um_bs.id = p.id_unidad_medida_base
            INNER JOIN lote_producto lt ON lt.id = rd.id_lote_producto
            INNER JOIN unidad_medida um_lt ON um_lt.id = lt.id_unidad_medida
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

    /**
     * Obtiene los detalles de una reposición formateados para la pantalla de recepción.
     */
    public static function get_detalles_entrega_reposicion(int $id_reposicion): array
    {
        return DB::select("
            SELECT 
                rd.id AS id_entrega_detalle,
                rd.id_prestamo_almacen_detalle AS id_solicitud_reabastecimiento_detalle,
                rd.id_prestamo_almacen_reposicion AS id_reabastecimiento_entrega,
                rd.cantidad_base,
                rd.cantidad_lote,
                rd.cantidad_solicitud,
                rd.estado AS estado_entrega_detalle,
                p.id AS id_producto,
                p.nombre AS producto,
                p.es_perecible,
                p.id_unidad_medida_base,
                um_base.abreviatura AS unidad_base_abv,
                srd.id_unidad_medida AS id_unidad_medida_solicitada,
                srd.contenido_por_presentacion AS contenido_por_presentacion_solicitado,
                um_sol.abreviatura AS unidad_medida_solicitud_abv,
                -- Campos adicionales para compatibilidad con componente de recepcion
                rd.id_lote_producto as id_lote_origen,
                lp.correlativo as correlativo_lote_origen,
                um_lote.abreviatura as unidad_lote_abv,
                lp.id_unidad_medida as id_unidad_medida_lote
            FROM 
                prestamo_almacen_reposicion_detalle rd
            INNER JOIN prestamo_almacen_detalle pad ON pad.id = rd.id_prestamo_almacen_detalle
            INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = pad.id_solicitud_reabastecimiento_detalle
            INNER JOIN producto p ON p.id = srd.id_producto
            INNER JOIN unidad_medida um_base ON um_base.id = p.id_unidad_medida_base
            INNER JOIN unidad_medida um_sol ON um_sol.id = srd.id_unidad_medida
            INNER JOIN lote_producto lp ON lp.id = rd.id_lote_producto
            INNER JOIN unidad_medida um_lote ON um_lote.id = lp.id_unidad_medida
            WHERE 
                rd.id_prestamo_almacen_reposicion = :id_reposicion
            ORDER BY p.nombre ASC
        ", ['id_reposicion' => $id_reposicion]);
    }

    /**
     * Marca un detalle de reposición como recibido.
     */
    public static function marcar_como_recibido(int $id_detalle, int $id_lote_ingreso): bool
    {
        return (bool) PrestamoAlmacenReposicionDetalle::where('id', $id_detalle)
            ->update([
                'estado' => EstadoDetalleReposicion::Recepcionado->value
            ]);
    }

    /**
     * Verifica si todos los detalles de la reposición están recibidos para cerrar la cabecera.
     */
    public static function verificar_y_completar_reposicion(int $id_reposicion): void
    {
        $pendientes = PrestamoAlmacenReposicionDetalle::where('id_prestamo_almacen_reposicion', $id_reposicion)
            ->where('estado', '!=', EstadoDetalleReposicion::Recepcionado->value)
            ->count();

        if ($pendientes === 0) {
            PrestamoAlmacenReposicion::where('id', $id_reposicion)
                ->update(['estado' => EstadoReposicion::Recepcionado->value]);
        }
    }
}
