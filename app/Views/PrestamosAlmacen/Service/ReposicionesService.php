<?php

namespace App\Views\PrestamosAlmacen\Service;

use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Data\AuxData;
use App\Views\PrestamosAlmacen\Data\KardexData;
use App\Views\PrestamosAlmacen\Data\LotesData;
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
        int $id_empleado_registro,
        string $fecha_hora_reposicion,
        ?string $observacion,
        array $items,
        ?array $evidencias = null
    ) {
        return DB::transaction(function () use (
            $id_prestamo_almacen,
            $id_almacen_entrega,
            $id_empleado_registro,
            $fecha_hora_reposicion,
            $observacion,
            $items,
            $evidencias
        ) {
            // 1. Validar existencia del préstamo
            $prestamo = ReposicionesData::get_prestamo_by_id($id_prestamo_almacen);
            if (!$prestamo) {
                return ApiResponse::error("El préstamo solicitado no existe.");
            }

            // 2. Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('prestamos_almacen_reposiciones', $evidencias);
            }

            // 3. Generar Correlativo RPS
            $correlativoData = ReposicionesData::get_nuevo_correlativo($id_almacen_entrega);

            // 4. Insertar la cabecera de la reposición
            $id_reposicion = ReposicionesData::insert_reposicion(
                $id_prestamo_almacen,
                $id_almacen_entrega,
                $id_empleado_registro,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                Carbon::parse($fecha_hora_reposicion)->toDateTimeString(),
                $observacion,
                $evidenciasData ? json_encode($evidenciasData) : null,
            );

            // 5. Procesar cada ítem de la reposición
            foreach ($items as $item) {
                $id_prestamo_detalle = (int) $item['id_prestamo_detalle'];
                $id_lote_producto = (int) $item['id_lote_producto'];
                $cantidad_base = (float) $item['cantidad_base'];
                $cantidad_lote = (float) $item['cantidad_lote'];
                $cantidad_solicitud = (float) $item['cantidad_solicitud'];

                // Validar Stock del lote de origen
                $lote = LotesData::get_lote_by_id($id_lote_producto);
                if (!$lote) {
                    return ApiResponse::error("El lote solicitado no existe.");
                }
                if ($lote->stock_actual_base < $cantidad_base) {
                    return ApiResponse::error("Stock insuficiente en el lote " . $lote->correlativo);
                }

                // A. Insertar detalle de la reposición
                ReposicionesData::insert_detalle_reposicion(
                    $id_reposicion,
                    $id_prestamo_detalle,
                    $id_lote_producto,
                    $cantidad_base,
                    $cantidad_lote,
                    $cantidad_solicitud
                );

                // B. Actualizar stock del lote (Salida por Reposición)
                $nuevo_stock = $lote->stock_actual - $cantidad_lote;
                $nuevo_stock_base = $lote->stock_actual_base - $cantidad_base;
                LotesData::update_stock_lote($id_lote_producto, $nuevo_stock, $nuevo_stock_base);

                // C. Incrementar cantidad repuesta en el detalle del préstamo
                ReposicionesData::increment_cantidad_repuesta($id_prestamo_detalle, $cantidad_solicitud, $cantidad_base);

                // D. Registrar Log de trazabilidad
                $glosa = "Reposición registrada por " . $cantidad_solicitud . " productos. Ref: " . $correlativoData['correlativo'];
                ReposicionesData::insert_detalle_log($id_prestamo_detalle, $id_empleado_registro, $glosa);

                // E. Registrar movimiento en Kardex
                $descripcion_kardex = "Salida por reposición de préstamo N° " . $prestamo->correlativo . " (Ref: " . $correlativoData['correlativo'] . ")";
                KardexData::registrar_kardex(
                    $id_lote_producto,
                    $id_reposicion,
                    $lote->stock_actual,
                    $lote->stock_actual_base,
                    $cantidad_lote,
                    $cantidad_base,
                    $nuevo_stock,
                    $nuevo_stock_base,
                    $descripcion_kardex
                );
            }

            return ApiResponse::success(
                null,
                "Reposición N° " . $correlativoData['correlativo'] . " registrada exitosamente"
            );
        });
    }
}
