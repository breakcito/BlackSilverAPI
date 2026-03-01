<?php

namespace App\Services;

use App\Models\PrestamoAlmacen;
use App\Models\PrestamoAlmacenDetalle;
use App\Models\PrestamoAlmacenDetalleLog;
use App\Shared\Enums\EstadoDetallePrestamo;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class PrestamoService
{
    /**
     * Lista préstamos realizados por un almacén solicitante.
     */
    public function obtener_prestamos(int $id_almacen, ?string $estado = null)
    {
        $lista = PrestamoAlmacen::get_prestamos($id_almacen, $estado);

        return ApiResponse::success($lista);
    }

    /**
     * Registra un nuevo préstamo con múltiples destinos (almacenes prestamistas).
     */
    public function crear_prestamo(
        int $id_usuario_solicitante,
        int $id_almacen_solicitante,
        ?string $motivo,
        string $fecha_prestamo,
        array $detalles
    ) {
        return DB::transaction(function () use (
            $id_usuario_solicitante,
            $id_almacen_solicitante,
            $motivo,
            $fecha_prestamo,
            $detalles
        ) {
            // 1. Generar Correlativo
            $prefijo = 'PRES';
            $nuevo_numero = CorrelativoHelper::proximoNumero('prestamo_almacen', 'numero_correlativo');

            // 2. Crear Cabecera
            $id_prestamo = PrestamoAlmacen::crear_prestamo(
                $id_almacen_solicitante,
                $id_usuario_solicitante,
                $prefijo,
                $nuevo_numero,
                $motivo,
                $fecha_prestamo
            );

            // 3. Crear Detalles y Logs Iniciales
            foreach ($detalles as $det) {
                $id_detalle = PrestamoAlmacenDetalle::crear_detalle(
                    $id_prestamo,
                    $det['id_producto'],
                    $det['id_unidad_medida'],
                    $det['id_almacen_prestamista'],
                    (float) $det['cantidad_solicitada'],
                    $det['comentario'] ?? null
                );

                PrestamoAlmacenDetalleLog::registrar_log(
                    $id_detalle,
                    $id_usuario_solicitante,
                    EstadoDetallePrestamo::Pendiente
                );
            }

            // 4. Retornar el objeto completo para el Front (Evitar recarga)
            $prestamoCompleto = PrestamoAlmacen::get_prestamo_by_id($id_prestamo);

            return ApiResponse::success([
                'mensaje' => 'Préstamo solicitado correctamente',
                'prestamo' => $prestamoCompleto,
            ]);
        });
    }

    /**
     * Obtiene el detalle completo de un préstamo por su ID.
     */
    public function obtener_por_id(int $id)
    {
        $prestamo = PrestamoAlmacen::get_prestamo_by_id($id);

        if (! $prestamo) {
            return ApiResponse::error('El préstamo no existe', 404);
        }

        return ApiResponse::success($prestamo);
    }

    /**
     * Obtiene la trazabilidad de un ítem específico del préstamo.
     */
    public function obtener_trazabilidad_detalle(int $id_detalle)
    {
        $trazabilidad = PrestamoAlmacenDetalleLog::get_trazabilidad($id_detalle);

        return ApiResponse::success($trazabilidad);
    }

    /**
     * Busca stock de un producto en otros almacenes (Sugerencia de lote para el carrito).
     */
    public function buscar_stock_global(int $id_producto, int $id_almacen_excluido)
    {
        $sql = "
        SELECT
            lp.id_almacen,
            a.nombre AS almacen,
            lp.id AS id_lote,
            CONCAT(lp.correlativo, '-', DATE_FORMAT(lp.created_at, '%y'), '-', LPAD(lp.numero_correlativo, 5, '0')) AS codigo_lote,
            lp.stock_actual,
            lp.id_unidad_medida,
            um.abreviatura AS unidad_medida,
            lp.fecha_vencimiento,
            DATEDIFF(lp.fecha_vencimiento, CURDATE()) AS dias_para_vencer
        FROM
            lote_producto lp
        INNER JOIN almacen a ON a.id = lp.id_almacen
        INNER JOIN unidad_medida um ON um.id = lp.id_unidad_medida
        WHERE
            lp.id_producto = :id_producto
            AND lp.id_almacen != :id_almacen
            AND lp.stock_actual > 0
            AND lp.estado = 'Activo'
        ORDER BY
            lp.fecha_vencimiento ASC,
            lp.created_at ASC
        ";

        $stocks = DB::select($sql, [
            'id_producto' => $id_producto,
            'id_almacen' => $id_almacen_excluido,
        ]);

        return ApiResponse::success($stocks);
    }
}
