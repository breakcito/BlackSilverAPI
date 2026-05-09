<?php

namespace App\Modules\OrdenesCompra\Service;

use App\Data\LotesProductosData;
use App\Modules\OrdenesCompra\Data\TransferenciaOCData;
use App\Services\LotesProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\OrdenCompra\EstadoOCTransferenciaDetalle;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Helpers\CorrelativoHelper;
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

            // Obtener correlativo
            $correlativo_data = CorrelativoHelper::generar(
                tabla: 'orden_compra_transferencia',
                prefijo: 'TRN',
                filtros: [
                    'id_almacen_destino' => $id_almacen_destino
                ],
                columnaFecha: 'fecha_hora_transferencia'
            );

            // Crear Cabecera de Transferencia
            $id_transferencia = TransferenciaOCData::crear_transferencia(
                id_almacen_destino: $id_almacen_destino,
                id_orden_compra_recepcion: $id_orden_compra_recepcion,
                id_empleado_transferencia: $id_empleado_transferencia,
                id_personal_recibe: $id_personal_recibe,
                correlativo: $correlativo_data['correlativo'],
                numero_correlativo: $correlativo_data['numero_correlativo'],
                fecha_hora_transferencia: $fecha_hora_transferencia,
                observacion: $observacion,
                evidencias: $evidenciasData
            );

            // Procesar e Insertar Detalles + Ajustar Stock + Kardex
            foreach ($detalles as $item) {
                $id_lote = (int) $item['id_lote_producto'];
                $lote = $lotesMap->get($id_lote);

                // Insertar detalle individualmente para obtener su ID (origen del kardex)
                $id_detalle = TransferenciaOCData::crear_detalles($id_transferencia, [
                    'id_orden_compra_recepcion_detalle' => $item['id_orden_compra_recepcion_detalle'],
                    'id_lote_producto' => $id_lote,
                    'cantidad_transferida_base' => $item['cantidad_transferida_base'],
                    'comentario' => $item['comentario'] ?? null,
                    'estado' => EstadoOCTransferenciaDetalle::EnDespacho,
                ]);

                $nuevo_stock_base = (float) $lote['stock_actual_base'] - (float) $item['cantidad_transferida_base'];

                LotesProductosService::update_stock(
                    id_lote: $id_lote,
                    id_origen: $id_detalle,
                    tabla_origen: 'orden_compra_transferencia_detalle',
                    tipo_origen: KardexOrigenMovimiento::Entrega,
                    nuevo_stock_base: $nuevo_stock_base,
                    descripcion: "Salida por transferencia de OC",
                );
            }

            // Recuperamos el objeto creado
            $transferencia = TransferenciaOCData::get_transferencia_by_id(id_transferencia: $id_transferencia);

            return ApiResponse::success(
                $transferencia,
                "Transferencia registrada exitosamente"
            );
        });
    }
}
