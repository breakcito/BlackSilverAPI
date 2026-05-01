<?php

namespace App\Modules\OrdenesCompra\Service;

use App\Data\KardexProductosData;
use App\Data\LotesProductosData;
use App\Models\OrdenCompraTransferencia;
use App\Models\OrdenCompraTransferenciaDetalle;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\OrdenCompra\EstadoOCTransferenciaDetalle;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class TransferenciaService
{
    /**
     * Registra una transferencia física de materiales recepcionados hacia su almacén destino real.
     */
    public static function registrar_transferencia(
        int $id_empleado_transferencia,
        int $id_orden_compra_recepcion,
        int $id_almacen_destino,
        int $id_personal_recibe,
        string $fecha_hora_transferencia,
        ?string $observacion,
        ?array $evidencias, // archivos
        array $detalles
    ) {
        return DB::transaction(function () use ($id_empleado_transferencia, $id_orden_compra_recepcion, $id_almacen_destino, $id_personal_recibe, $fecha_hora_transferencia, $observacion, $evidencias, $detalles) {
            // Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('ordenes_compra_transferencias', $evidencias);
            }

            // Pre-cargar todos los lotes en una sola consulta
            $ids_lotes = array_map(fn($i) => (int) $i['id_lote_producto'], $detalles);
            $lotesMap = collect(LotesProductosData::get_lote_simple_by_id($ids_lotes))
                ->keyBy('id_lote');

            // Validar Stock
            foreach ($detalles as $item) {
                $lote = $lotesMap->get((int) $item['id_lote_producto']);
                if (!$lote || $lote['stock_actual_base'] < $item['cantidad_transferida_base']) {
                    return ApiResponse::error("Stock insuficiente en el lote: " . ($lote['correlativo'] ?? 'ID: ' . $item['id_lote_producto']));
                }
            }

            // Crear Cabecera de Transferencia
            $id_transferencia = OrdenCompraTransferencia::crear_transferencia(
                id_almacen_destino: $id_almacen_destino,
                id_orden_compra_recepcion: $id_orden_compra_recepcion,
                id_empleado_transferencia: $id_empleado_transferencia,
                id_personal_recibe: $id_personal_recibe,
                fecha_hora_transferencia: $fecha_hora_transferencia,
                observacion: $observacion,
                evidencias: $evidenciasData
            );

            // Preparar e Insertar Detalles de Transferencia en Batch
            $detallesToInsert = [];
            foreach ($detalles as $item) {
                $detallesToInsert[] = [
                    'id_orden_compra_recepcion_detalle' => $item['id_orden_compra_recepcion_detalle'],
                    'id_lote_producto' => $item['id_lote_producto'],
                    'cantidad_transferida_base' => $item['cantidad_transferida_base'],
                    'comentario' => $item['comentario'] ?? null,
                    'estado' => EstadoOCTransferenciaDetalle::EnDespacho,
                ];
            }
            OrdenCompraTransferenciaDetalle::crear_detalle($id_transferencia, $detallesToInsert);

            // Ajuste de Stock y Kardex
            foreach ($detalles as $item) {
                $id_lote = $item['id_lote_producto'];
                $lote = $lotesMap->get((int) $id_lote);

                $stock_anterior = $lote['stock_actual'];
                $stock_anterior_base = $lote['stock_actual_base'];

                // Calcular cantidad lote basada en cantidad base
                $cantidad_lote = $item['cantidad_transferida_base'] / $lote['contenido_por_presentacion'];

                $nuevo_stock = $stock_anterior - $cantidad_lote;
                $nuevo_stock_base = $stock_anterior_base - $item['cantidad_transferida_base'];

                // Actualizar Stock del Lote en Almacén Origen
                LotesProductosData::update_stock($id_lote, $nuevo_stock, $nuevo_stock_base);

                // Registrar Kardex (Salida por Transferencia)
                KardexProductosData::registrar_kardex(
                    id_lote: $id_lote,
                    id_origen: $id_transferencia, // vinculamos a la cabecera
                    tipo_movimiento: KardexTipoMovimiento::Salida,
                    tipo_origen: KardexOrigenMovimiento::Entrega, // Aprobado por el usuario
                    descripcion: "Salida por transferencia de OC",
                    stock_anterior: $stock_anterior,
                    stock_anterior_base: $stock_anterior_base,
                    cantidad_movimiento: $cantidad_lote,
                    cantidad_movimiento_base: $item['cantidad_transferida_base'],
                    nuevo_stock: $nuevo_stock,
                    nuevo_stock_base: $nuevo_stock_base
                );
            }

            // Recuperamos el objeto creado
            $transferencia = OrdenCompraTransferencia::get_transferencias(id_transferencia: $id_transferencia);

            return ApiResponse::success(
                $transferencia,
                "Transferencia registrada exitosamente"
            );
        });
    }
}
