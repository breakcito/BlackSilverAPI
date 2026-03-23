<?php

namespace App\Views\PrestamosAlmacenAtencion;

use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class PrestamosAtencionService
{
    // =========================================================================
    // AUXILIARES
    // =========================================================================

    /**
     * Devuelve los almacenes donde el empleado es responsable.
     */
    public static function get_almacenes_autorizados(int $id_empleado)
    {
        $data = PrestamosAtencionData::get_almacenes_autorizados($id_empleado);
        return ApiResponse::success($data);
    }

    /**
     * Lista de empleados activos (para seleccionar quién entrega y quién recibe).
     */
    public static function get_empleados()
    {
        $data = PrestamosAtencionData::get_empleados();
        return ApiResponse::success($data);
    }

    /**
     * Lotes disponibles de un producto en el almacén prestamista (para elegir de dónde despachar).
     */
    public static function get_lotes_disponibles(int $id_producto, int $id_almacen)
    {
        $data = PrestamosAtencionData::get_lotes_disponibles($id_producto, $id_almacen);
        return ApiResponse::success($data);
    }

    // =========================================================================
    // PRÉSTAMOS
    // =========================================================================

    /**
     * Listado de préstamos donde el almacén actúa como prestamista (le llegaron solicitudes).
     */
    public static function get_prestamos_por_almacen(int $id_almacen, string $mes, string $yearcito)
    {
        $prestamos = PrestamosAtencionData::get_prestamos_por_almacen($id_almacen, $mes, $yearcito);

        // Adjuntar detalles (ítems del préstamo) a cada registro
        foreach ($prestamos as $prestamo) {
            $prestamo->detalles = PrestamosAtencionData::get_detalles_prestamo((int) $prestamo->id_prestamo);
        }

        return ApiResponse::success($prestamos);
    }

    /**
     * Detalle completo de un préstamo: cabecera + ítems + historial de entregas.
     */
    public static function get_prestamo_detalle(int $id_prestamo)
    {
        $detalles  = PrestamosAtencionData::get_detalles_prestamo($id_prestamo);
        $entregas  = PrestamosAtencionData::get_entregas_por_prestamo($id_prestamo);

        foreach ($entregas as $entrega) {
            $entrega->detalles = PrestamosAtencionData::get_detalles_entrega((int) $entrega->id_entrega);
        }

        return ApiResponse::success([
            'detalles' => $detalles,
            'entregas' => $entregas,
        ]);
    }

    // =========================================================================
    // DESPACHO (Entrega desde el Almacén Prestamista)
    // =========================================================================

    /**
     * Registra el despacho de productos del préstamo.
     *
     * Por cada ítem del despacho:
     *  - Verifica que el lote tenga stock suficiente.
     *  - Resta del lote origen (stock del prestamista).
     *  - Registra Kardex de Salida.
     *  - Crea el registro en prestamo_almacen_entrega y sus detalles.
     *
     * @param int    $id_prestamo
     * @param int    $id_empleado_entrega   (quien despacha, tomado del JWT)
     * @param int    $id_empleado_recibe    (quien recibe, seleccionado en el form)
     * @param string $fecha_hora_entrega
     * @param string|null $observacion
     * @param array  $detalles  [{id_prestamo_detalle, id_lote_salida, cantidad, cantidad_base}]
     */
    public static function registrar_despacho(
        int $id_prestamo,
        int $id_empleado_entrega,
        int $id_empleado_recibe,
        string $fecha_hora_entrega,
        ?string $observacion,
        array $detalles
    ) {
        return DB::transaction(function () use (
            $id_prestamo, $id_empleado_entrega, $id_empleado_recibe,
            $fecha_hora_entrega, $observacion, $detalles
        ) {
            // 1. Validar stock en tiempo real
            foreach ($detalles as $item) {
                $lote = PrestamosAtencionData::get_lote_by_id((int) $item['id_lote_salida']);
                if (!$lote || $lote->stock_actual_base < (float) $item['cantidad_base']) {
                    $correlativo = $lote?->correlativo ?? "ID #{$item['id_lote_salida']}";
                    return ApiResponse::error("Stock insuficiente en el lote {$correlativo}. Recarga los lotes e intenta nuevamente.");
                }
            }

            // 2. Generar correlativo de entrega
            $correlativoData = PrestamosAtencionData::get_nuevo_correlativo();

            // 3. Crear cabecera de entrega
            $id_entrega = PrestamosAtencionData::crear_entrega(
                $id_prestamo,
                $id_empleado_entrega,
                $id_empleado_recibe,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                $fecha_hora_entrega,
                $observacion
            );

            // 4. Procesar cada ítem del despacho
            foreach ($detalles as $item) {
                $id_lote       = (int) $item['id_lote_salida'];
                $cantidad      = (float) $item['cantidad'];        // en unidad de presentación
                $cantidad_base = (float) $item['cantidad_base'];   // en unidad base

                // 4a. Crear detalle de entrega
                $id_detalle_entrega = PrestamosAtencionData::crear_detalle_entrega(
                    $id_entrega,
                    (int) $item['id_prestamo_detalle'],
                    $id_lote,
                    $cantidad
                );

                // 4b. Cargar lote actual para Kardex
                $lote = PrestamosAtencionData::get_lote_by_id($id_lote);
                $stock_anterior      = $lote->stock_actual;
                $stock_anterior_base = $lote->stock_actual_base;
                $nuevo_stock         = $stock_anterior      - ($cantidad * $lote->contenido_por_presentacion > 0 ? 1 : $cantidad);
                $nuevo_stock_base    = $stock_anterior_base - $cantidad_base;

                // Recálculo correcto usando contenido_por_presentacion del lote
                $contenido = (float)($lote->contenido_por_presentacion ?: 1);
                $nuevo_stock      = $stock_anterior      - ($cantidad_base / $contenido);
                $nuevo_stock_base = $stock_anterior_base - $cantidad_base;

                // 4c. Actualizar stock del lote
                PrestamosAtencionData::update_lote_stock($id_lote, max(0, $nuevo_stock), max(0, $nuevo_stock_base));

                // 4d. Kardex de Salida
                PrestamosAtencionData::registrar_kardex_salida(
                    $id_lote,
                    $id_detalle_entrega,
                    $stock_anterior,
                    $stock_anterior_base,
                    $cantidad_base / $contenido,
                    $cantidad_base,
                    max(0, $nuevo_stock),
                    max(0, $nuevo_stock_base),
                    "Salida por despacho de préstamo N° {$correlativoData['correlativo']}"
                );

                // 4e. Actualizar acumulado despachado en el detalle del préstamo
                PrestamosAtencionData::incrementar_despachado((int) $item['id_prestamo_detalle'], $cantidad_base);
            }

            return ApiResponse::success(
                ['correlativo' => $correlativoData['correlativo'], 'id_entrega' => $id_entrega],
                "Despacho N° {$correlativoData['correlativo']} registrado exitosamente"
            );
        });
    }
}
