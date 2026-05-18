<?php

namespace App\Modules\OrdenesCompra\Service;

use App\Data\LotesProductosData;
use App\Modules\OrdenesCompra\Data\TransferenciaOCData;
use App\Services\LotesProductosService;
use App\Services\ActivosFijosService;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\OrdenCompra\EstadoOCTransferenciaDetalle;
use App\Shared\Enums\_Generic\TipoBien;
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
        ?int $id_almacen_destino,
        int $id_personal_recibe,
        string $fecha_hora_transferencia,
        array $detalles,
        ?string $observacion = null,
        ?array $evidencias = null, // archivos
        ?int $id_mina_destino = null,
    ) {
        return DB::transaction(function () use ($id_empleado_transferencia, $id_orden_compra_recepcion, $id_almacen_destino, $id_personal_recibe, $fecha_hora_transferencia, $observacion, $evidencias, $detalles, $id_mina_destino) {
            // Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('ordenes_compra_transferencias', $evidencias);
            }

            // Pre-cargar detalles de la recepción para clasificar tipo_bien de cada ítem
            $ids_recepcion_detalles = array_map(fn($i) => (int) $i['id_orden_compra_recepcion_detalle'], $detalles);
            $recepDetalles = DB::table('orden_compra_recepcion_detalle as rcd')
                ->join('orden_compra_detalle as ocd', 'ocd.id', '=', 'rcd.id_orden_compra_detalle')
                ->join('producto as prod', 'prod.id', '=', 'ocd.id_producto')
                ->join('categoria as cat', 'cat.id', '=', 'prod.id_categoria')
                ->leftJoin('activo_fijo as act', 'act.id', '=', 'rcd.id_activo_fijo')
                ->whereIn('rcd.id', $ids_recepcion_detalles)
                ->select(
                    'rcd.id',
                    'rcd.id_activo_fijo',
                    'cat.clasificacion_bien as tipo_bien',
                    'act.id_mina as act_id_mina'
                )
                ->get()
                ->keyBy('id');

            $commonItems = [];
            foreach ($detalles as $item) {
                $rcdId = (int) $item['id_orden_compra_recepcion_detalle'];
                $info = $recepDetalles->get($rcdId);
                if ($info && $info->tipo_bien === TipoBien::ActivoFijo->value) {
                    $assetItems[] = $item;
                } else {
                    $commonItems[] = $item;
                }
            }

            // Validar Stock solo para productos comunes
            if (!empty($commonItems)) {
                $ids_lotes = array_map(fn($i) => (int) $i['id_lote_producto'], $commonItems);
                $lotesMap = collect(LotesProductosData::get_lote_simple_by_id($ids_lotes))
                    ->keyBy('id_lote');

                foreach ($commonItems as $item) {
                    $lote = $lotesMap->get((int) $item['id_lote_producto']);
                    if (!$lote || $lote['stock_actual_base'] < $item['cantidad_transferida_base']) {
                        return ApiResponse::error("Stock insuficiente en el lote: " . ($lote['correlativo'] ?? 'ID: ' . $item['id_lote_producto']));
                    }
                }
            }

            // Obtener correlativo
            $filtros = [];
            if ($id_almacen_destino !== null) {
                $filtros['id_almacen_destino'] = $id_almacen_destino;
            } elseif ($id_mina_destino !== null) {
                $filtros['id_mina_destino'] = $id_mina_destino;
            }

            $correlativo_data = CorrelativoHelper::generar(
                tabla: 'orden_compra_transferencia',
                prefijo: 'TRN',
                filtros: $filtros,
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
                evidencias: $evidenciasData,
                id_mina_destino: $id_mina_destino
            );

            // Procesar e Insertar Detalles + Ajustar Stock + Kardex
            foreach ($detalles as $item) {
                $rcdId = (int) $item['id_orden_compra_recepcion_detalle'];
                $info = $recepDetalles->get($rcdId);

                if ($info && $info->tipo_bien === TipoBien::ActivoFijo->value) {
                    $id_activo_fijo = (int) $info->id_activo_fijo;

                    // Insertar detalle vinculando el activo fijo
                    $id_detalle = TransferenciaOCData::crear_detalles($id_transferencia, [
                        'id_orden_compra_recepcion_detalle' => $rcdId,
                        'id_lote_producto' => 0,
                        'id_activo_fijo' => $id_activo_fijo,
                        'cantidad_transferida_base' => 1,
                        'comentario' => $item['comentario'] ?? null,
                        'estado' => EstadoOCTransferenciaDetalle::EnDespacho,
                    ]);

                    if ($id_mina_destino !== null) {
                        $tipo_mov = (!empty($info->act_id_mina))
                            ? MovimientoActivoFijo::DeMinaAMina
                            : MovimientoActivoFijo::DeAlmacenAMina;

                        ActivosFijosService::new_ubicacion(
                            id_activo: $id_activo_fijo,
                            tipo_movimiento: $tipo_mov,
                            id_almacen: null,
                            id_mina: $id_mina_destino,
                            descripcion: "Transferencia de OC a Mina",
                            fecha_hora_movimiento: $fecha_hora_transferencia
                        );
                    } else {
                        $tipo_mov = (!empty($info->act_id_mina))
                            ? MovimientoActivoFijo::DeMinaAAlmacen
                            : MovimientoActivoFijo::DeAlmacenAAlmacen;

                        ActivosFijosService::new_ubicacion(
                            id_activo: $id_activo_fijo,
                            tipo_movimiento: $tipo_mov,
                            id_almacen: $id_almacen_destino,
                            id_mina: null,
                            descripcion: "Transferencia de OC a Almacén",
                            fecha_hora_movimiento: $fecha_hora_transferencia
                        );
                    }
                } else {
                    $id_lote = (int) $item['id_lote_producto'];

                    // Insertar detalle para producto común
                    $id_detalle = TransferenciaOCData::crear_detalles($id_transferencia, [
                        'id_orden_compra_recepcion_detalle' => $rcdId,
                        'id_lote_producto' => $id_lote,
                        'cantidad_transferida_base' => $item['cantidad_transferida_base'],
                        'comentario' => $item['comentario'] ?? null,
                        'estado' => EstadoOCTransferenciaDetalle::EnDespacho,
                    ]);

                    LotesProductosService::update_stock(
                        id_lote: $id_lote,
                        id_origen: $id_detalle,
                        tabla_origen: 'orden_compra_transferencia_detalle',
                        tipo_origen: KardexOrigenMovimiento::Entrega,
                        tipo_movimiento: KardexTipoMovimiento::Salida,
                        cantidad_movimiento_base: (float) $item['cantidad_transferida_base'],
                        descripcion: "Salida por transferencia de OC",
                    );
                }
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
