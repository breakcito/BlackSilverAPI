<?php

namespace App\Views\PrestamosAlmacen\Service;

use App\Data\KardexProductosData;
use App\Data\LotesProductosData;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Data\PrestamosData;
use App\Views\PrestamosAlmacen\Data\ReposicionesData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ReposicionesService
{
    /**
     * Obtiene el historial de reposiciones de un préstamo.
     */
    public static function get_historial(int $id_prestamo_almacen)
    {
        $data = ReposicionesData::get_reposiciones_by_prestamo($id_prestamo_almacen);
        foreach ($data as $repo) {
            $repo->evidencias = $repo->evidencias ? json_decode($repo->evidencias) : null;
            $repo->detalles = ReposicionesData::get_detalles_reposicion((int) $repo->id_reposicion);
        }
        return ApiResponse::success($data);
    }

    /**
     * Registra una nueva reposición de stock para un préstamo entre almacenes.
     */
    public static function registrar_reposicion(
        int $id_prestamo_almacen,
        int $id_almacen_entrega,
        int $id_empleado_entrega,
        string $fecha_hora_reposicion,
        //
        // [{id_prestamo_detalle, id_lote_producto, cantidad_base, cantidad_lote, cantidad_prestamo}]
        array $items,
        //
        ?string $observacion,
        ?array $evidencias = null
    ) {
        return DB::transaction(function () use (
            $id_prestamo_almacen,
            $id_almacen_entrega,
            $id_empleado_entrega,
            $fecha_hora_reposicion,
            $observacion,
            $items,
            $evidencias
        ) {
            // 1. Obtener el correlativo del prestamo
            $prestamo = PrestamosData::get_correlativo_by_id($id_prestamo_almacen);

            // 2. Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ReposicionesData::guardar_evidencias($evidencias);
            }

            // 3. Generar Correlativo RPS
            $correlativoData = ReposicionesData::get_nuevo_correlativo($id_almacen_entrega);

            // 4. Insertar la cabecera de la reposición
            $id_reposicion = ReposicionesData::crear_reposicion(
                $id_prestamo_almacen,
                $id_almacen_entrega,
                $id_empleado_entrega,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                Carbon::parse($fecha_hora_reposicion)->toDateTimeString(),
                $observacion,
                $evidenciasData,
            );

            // 5. Procesar cada ítem de la reposición
            foreach ($items as $item) {
                $id_prestamo_detalle = (int) $item['id_prestamo_detalle'];
                $id_lote_producto = (int) $item['id_lote_producto'];
                $cantidad_base = (float) $item['cantidad_base'];
                $cantidad_lote = (float) $item['cantidad_lote'];
                $cantidad_prestamo = (float) $item['cantidad_prestamo'];

                // Validar Stock del lote de origen
                $lote = LotesProductosData::get_lote_simple_by_id($id_lote_producto);
                if ($lote['stock_actual_base'] < $cantidad_base) {
                    return ApiResponse::error("Stock insuficiente en el lote " . $lote['correlativo']);
                }

                // A. Insertar detalle de la reposición
                ReposicionesData::crear_detalle_reposicion(
                    $id_reposicion,
                    $id_prestamo_detalle,
                    $id_lote_producto,
                    $cantidad_base,
                    $cantidad_lote,
                    $cantidad_prestamo
                );

                // B. Actualizar stock del lote (Salida por Reposición)
                $nuevo_stock = $lote['stock_actual'] - $cantidad_lote;
                $nuevo_stock_base = $lote['stock_actual_base'] - $cantidad_base;
                LotesProductosData::update_stock($id_lote_producto, $nuevo_stock, $nuevo_stock_base);

                // C. Incrementar cantidad repuesta en el detalle del préstamo
                PrestamosData::incrementar_cantidad_repuesta($id_prestamo_detalle, $cantidad_prestamo, $cantidad_base);

                // D. Registrar Log de trazabilidad en el detalle del préstamo
                $glosa = "Reposición N° " . $correlativoData['correlativo'] . " registrada por " . $cantidad_prestamo . " productos";
                PrestamosData::crear_log($id_prestamo_detalle, $id_empleado_entrega, $glosa);

                // E. Registrar movimiento en Kardex
                $descripcion_kardex = "Salida por reposición de préstamo N° " . $prestamo->correlativo . " (Ref: " . $correlativoData['correlativo'] . ")";
                KardexProductosData::registrar_kardex(
                    id_lote: $id_lote_producto,
                    id_origen: $id_reposicion,
                    tipo_movimiento: TipoMovimiento::Salida,
                    tipo_origen: OrigenMovimiento::Reposicion,
                    descripcion: $descripcion_kardex,
                    stock_anterior: $lote['stock_actual'],
                    stock_anterior_base: $lote['stock_actual_base'],
                    cantidad_movimiento: $cantidad_lote,
                    cantidad_movimiento_base: $cantidad_base,
                    nuevo_stock: $nuevo_stock,
                    nuevo_stock_base: $nuevo_stock_base,
                );
            }

            return ApiResponse::success(
                null,
                "Reposición N° " . $correlativoData['correlativo'] . " registrada exitosamente"
            );
        });
    }
}
