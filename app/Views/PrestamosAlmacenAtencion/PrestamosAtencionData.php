<?php

namespace App\Views\PrestamosAlmacenAtencion;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Models\PrestamoAlmacenEntrega;
use App\Models\PrestamoAlmacenEntregaDetalle;
use App\Shared\Enums\PrestamoAlmacen\EstadoEntregaPrestamo;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Helpers\Periodo;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use Illuminate\Support\Facades\DB;

class PrestamosAtencionData
{
    // =========================================================================
    // AUXILIARES
    // =========================================================================

    /**
     * Obtiene los almacenes donde el empleado es responsable (no principales).
     */
    public static function get_almacenes_autorizados(int $id_empleado): array
    {
        return DB::select('
            SELECT DISTINCT
                alm.id AS id_almacen,
                alm.nombre
            FROM
                almacen alm
            INNER JOIN responsable_almacen res ON res.id_almacen = alm.id
            WHERE
                alm.estado = "Activo"
                AND alm.es_principal != 1
                AND res.estado = "Activo"
                AND res.id_empleado = :id_empleado
        ', ['id_empleado' => $id_empleado]);
    }

    /**
     * Obtiene los empleados activos para seleccionar como entregador o receptor.
     */
    public static function get_empleados(): array
    {
        return DB::select('
            SELECT
                emp.id AS id_empleado,
                CONCAT(emp.nombre, " ", emp.apellido) AS nombre_completo,
                emp.dni,
                emp.path_foto
            FROM empleado emp
            WHERE emp.estado = "Activo"
            ORDER BY emp.nombre ASC
        ');
    }

    /**
     * Obtiene los lotes disponibles de un producto en un almacén (para el despacho).
     */
    public static function get_lotes_disponibles(int $id_producto, int $id_almacen): array
    {
        return DB::select('
            SELECT
                lp.id AS id_lote,
                lp.id_producto,
                lp.correlativo,
                lp.stock_actual,
                lp.stock_actual_base,
                lp.contenido_por_presentacion,
                um.nombre AS unidad_medida,
                um.abreviatura AS unidad_medida_abv,
                lp.fecha_vencimiento,
                DATEDIFF(lp.fecha_vencimiento, NOW()) AS dias_para_vencer
            FROM lote_producto lp
            INNER JOIN unidad_medida um ON um.id = lp.id_unidad_medida
            WHERE
                lp.id_producto = :id_producto
                AND lp.id_almacen = :id_almacen
                AND lp.stock_actual_base > 0
                AND lp.estado = "Activo"
            ORDER BY lp.fecha_vencimiento ASC, lp.created_at ASC
        ', ['id_producto' => $id_producto, 'id_almacen' => $id_almacen]);
    }

    /**
     * Obtiene un lote por su ID para validar stock.
     */
    public static function get_lote_by_id(int $id_lote)
    {
        return LoteProducto::select('id', 'correlativo', 'id_producto', 'contenido_por_presentacion', 'stock_actual', 'stock_actual_base')
            ->where('id', $id_lote)
            ->first();
    }

    /**
     * Actualiza el stock de un lote.
     */
    public static function update_lote_stock(int $id_lote, float $nuevo_stock, float $nuevo_stock_base): void
    {
        LoteProducto::where('id', $id_lote)->update([
            'stock_actual'      => $nuevo_stock,
            'stock_actual_base' => $nuevo_stock_base,
        ]);
    }

    /**
     * Registra un movimiento de Kardex (salida desde el almacén prestamista).
     */
    public static function registrar_kardex_salida(
        int $id_lote,
        int $id_detalle_entrega,
        float $stock_anterior,
        float $stock_anterior_base,
        float $cantidad_lote,
        float $cantidad_base,
        float $nuevo_stock,
        float $nuevo_stock_base,
        string $descripcion
    ): void {
        KardexProducto::insert([
            'id_lote_producto'          => $id_lote,
            'id_origen'                 => $id_detalle_entrega,
            'tipo_origen'               => OrigenMovimiento::Entrega->value,
            'tipo_movimiento'           => TipoMovimiento::Salida->value,
            'descripcion'               => $descripcion,
            'stock_anterior'            => $stock_anterior,
            'stock_anterior_base'       => $stock_anterior_base,
            'cantidad_movimiento'       => $cantidad_lote,
            'cantidad_movimiento_base'  => $cantidad_base,
            'stock_resultante'          => $nuevo_stock,
            'stock_resultante_base'     => $nuevo_stock_base,
            'created_at'                => now(),
        ]);
    }

    // =========================================================================
    // PRÉSTAMOS (listado y detalle)
    // =========================================================================

    /**
     * Obtiene los préstamos recibidos por un almacén (como prestamista).
     * Filtra por mes/año y estado si se proporcionan.
     */
    public static function get_prestamos_por_almacen(int $id_almacen, string $mes, string $yearcito): array
    {
        return DB::select('
            SELECT
                pa.id AS id_prestamo,
                pa.correlativo,
                pa.numero_correlativo,
                pa.fecha_hora_prestamo,
                pa.fecha_limite_devolucion,
                pa.created_at,
                pa.estado,
                alm_sol.nombre AS almacen_solicitante,
                alm_sol.id    AS id_almacen_solicitante,
                CONCAT(e.nombre, " ", e.apellido) AS registrado_por
            FROM
                prestamo_almacen pa
            INNER JOIN solicitud_reabastecimiento sr ON sr.id = pa.id_solicitud_reabastecimiento
            INNER JOIN almacen alm_sol ON alm_sol.id = sr.id_almacen_solicitante
            INNER JOIN empleado e ON e.id = pa.id_empleado_registro
            WHERE
                pa.id_almacen_prestamista = :id_almacen
                AND MONTH(pa.created_at) = :mes
                AND YEAR(pa.created_at) = :yearcito
            ORDER BY pa.created_at DESC
        ', ['id_almacen' => $id_almacen, 'mes' => $mes, 'yearcito' => $yearcito]);
    }

    /**
     * Obtiene el detalle de los ítems de un préstamo específico.
     */
    public static function get_detalles_prestamo(int $id_prestamo): array
    {
        return DB::select('
            SELECT
                pad.id AS id_prestamo_detalle,
                pad.cantidad_solicitada,
                pad.cantidad_solicitada_base,
                pad.comentario,
                pad.estado,
                srd.id_producto,
                prod.nombre AS producto,
                um.nombre AS unidad_medida,
                um.abreviatura AS unidad_medida_abv,
                srd.contenido_por_presentacion
            FROM
                prestamo_almacen_detalle pad
            INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = pad.id_solicitud_reabastecimiento_detalle
            INNER JOIN producto prod ON prod.id = srd.id_producto
            INNER JOIN unidad_medida um ON um.id = srd.id_unidad_medida
            WHERE pad.id_prestamo_almacen = :id_prestamo
            ORDER BY prod.nombre ASC
        ', ['id_prestamo' => $id_prestamo]);
    }

    // =========================================================================
    // ENTREGAS (cabecera y detalle)
    // =========================================================================

    /**
     * Genera el correlativo para una nueva entrega de préstamo.
     */
    public static function get_nuevo_correlativo(): array
    {
        return CorrelativoHelper::generar(
            tabla: 'prestamo_almacen_entrega',
            prefijo: 'PRSE',
            filtros: [],
            longitud: 5,
            periodo: Periodo::Anual,
            campoFecha: 'created_at'
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
        ?string $observacion
    ): int {
        return PrestamoAlmacenEntrega::insertGetId([
            'id_prestamo_almacen'   => $id_prestamo_almacen,
            'id_empleado_entrega'   => $id_empleado_entrega,
            'id_empleado_recibe'    => $id_empleado_recibe,
            'correlativo'           => $correlativo,
            'numero_correlativo'    => $numero_correlativo,
            'fecha_hora_entrega'    => $fecha_hora_entrega,
            'observacion'           => $observacion,
            'created_at'            => now(),
            'estado'                => EstadoEntregaPrestamo::EnDespacho->value,
        ]);
    }

    /**
     * Crea un detalle de entrega (un lote de salida para un ítem del préstamo).
     */
    public static function crear_detalle_entrega(
        int $id_entrega,
        int $id_prestamo_detalle,
        int $id_lote_salida,
        float $cantidad
    ): int {
        return PrestamoAlmacenEntregaDetalle::insertGetId([
            'id_prestamo_almacen_entrega' => $id_entrega,
            'id_prestamo_almacen_detalle' => $id_prestamo_detalle,
            'id_lote_salida'              => $id_lote_salida,
            'id_lote_ingreso'             => null, // Se llenará cuando el receptor confirme
            'cantidad'                    => $cantidad,
            'estado'                      => EstadoEntregaPrestamo::EnDespacho->value,
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

    /**
     * Obtiene los detalles de una entrega específica (lotes, cantidades, producto).
     */
    public static function get_detalles_entrega(int $id_entrega): array
    {
        return DB::select('
            SELECT
                paed.id AS id_entrega_detalle,
                paed.id_prestamo_almacen_detalle,
                paed.cantidad,
                paed.estado,
                pad.comentario,
                lote.id AS id_lote_salida,
                lote.correlativo AS correlativo_lote,
                lote.fecha_vencimiento,
                DATEDIFF(lote.fecha_vencimiento, NOW()) AS dias_para_vencer,
                prod.id AS id_producto,
                prod.nombre AS producto,
                um.nombre AS unidad_medida,
                um.abreviatura AS unidad_medida_abv,
                srd.contenido_por_presentacion
            FROM
                prestamo_almacen_entrega_detalle paed
            INNER JOIN prestamo_almacen_detalle pad ON pad.id = paed.id_prestamo_almacen_detalle
            INNER JOIN lote_producto lote ON lote.id = paed.id_lote_salida
            INNER JOIN solicitud_reabastecimiento_detalle srd ON srd.id = pad.id_solicitud_reabastecimiento_detalle
            INNER JOIN producto prod ON prod.id = srd.id_producto
            INNER JOIN unidad_medida um ON um.id = srd.id_unidad_medida
            WHERE paed.id_prestamo_almacen_entrega = :id_entrega
            ORDER BY prod.nombre ASC
        ', ['id_entrega' => $id_entrega]);
    }

    /**
     * Actualiza la cantidad despachada en el detalle del préstamo.
     */
    public static function incrementar_despachado(int $id_prestamo_detalle, float $cantidad_base): void
    {
        DB::statement('
            UPDATE prestamo_almacen_detalle
            SET cantidad_despachada_base = COALESCE(cantidad_despachada_base, 0) + :cantidad
            WHERE id = :id
        ', ['cantidad' => $cantidad_base, 'id' => $id_prestamo_detalle]);
    }
}
