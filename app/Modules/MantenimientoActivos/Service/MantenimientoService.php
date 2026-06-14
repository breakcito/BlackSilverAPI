<?php

namespace App\Modules\MantenimientoActivos\Service;

use App\Models\ActivoFijo;
use App\Models\RequerimientoAlmacenEntregaDetalleConsumo;
use App\Modules\MantenimientoActivos\Data\MantenimientoData;
use App\Modules\ControlConsumoActivos\Data\EntregasData;
use App\Shared\Enums\RequerimientoAlmacen\EstadoConsumoDetalleEntregaReq;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class MantenimientoService
{
    /**
     * Obtener listado de mantenimientos de activos por periodo (mes y año).
     */
    public static function get_mantenimientos(int $mes, int $yearcito, ?int $id_activo_fijo = null): array
    {
        $data = MantenimientoData::get_mantenimientos($mes, $yearcito, $id_activo_fijo);

        foreach ($data as $row) {
            $row->otros_gastos = $row->otros_gastos ? json_decode($row->otros_gastos) : null;
            $row->evidencias = $row->evidencias ? json_decode($row->evidencias) : null;
        }

        return ApiResponse::success($data);
    }

    /**
     * Obtener productos despachados pendientes de consumo para un activo específico.
     */
    public static function get_productos_despachados(int $id_activo_fijo): array
    {
        $data = MantenimientoData::get_productos_despachados($id_activo_fijo);
        return ApiResponse::success($data);
    }

    /**
     * Registrar un nuevo mantenimiento y asociar sus consumos y recalcular alertas del activo.
     *
     * @param int $id_empleado_registro
     * @param array $data
     * @param array $evidencias
     */
    public static function crear_mantenimiento(int $id_empleado_registro, array $data, array $evidencias): array
    {
        return DB::transaction(function () use ($id_empleado_registro, $data, $evidencias) {
            $activo = ActivoFijo::where('id', $data['id_activo_fijo'])->first();
            if (!$activo) {
                return ApiResponse::error('El activo fijo no existe.');
            }

            // Procesar evidencias
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('mantenimientos', $evidencias);
            }

            // Procesar otros gastos
            $otrosGastosJson = null;
            if (isset($data['otros_gastos'])) {
                // Si ya viene formateado como string, o lo encodeamos
                $otrosGastosJson = is_array($data['otros_gastos']) ? json_encode($data['otros_gastos']) : $data['otros_gastos'];
            }

            // Registrar cabecera
            $mantenimientoPayload = [
                'id_activo_fijo' => $data['id_activo_fijo'],
                'id_empleado_registro' => $id_empleado_registro,
                'id_mina' => isset($data['id_mina']) ? (int) $data['id_mina'] : null,
                'id_almacen' => isset($data['id_almacen']) ? (int) $data['id_almacen'] : null,
                'id_empleado_supervisor' => isset($data['id_empleado_supervisor']) ? (int) $data['id_empleado_supervisor'] : null,
                'id_proveedor' => isset($data['id_proveedor']) ? (int) $data['id_proveedor'] : null,
                'id_personal_externo' => isset($data['id_personal_externo']) ? (int) $data['id_personal_externo'] : null,
                'id_empleado_ejecutor' => isset($data['id_empleado_ejecutor']) ? (int) $data['id_empleado_ejecutor'] : null,
                'fecha_hora_mantenimiento' => $data['fecha_hora_mantenimiento'],
                'observacion' => $data['observacion'] ?? null,
                'lugar_trabajo' => $data['lugar_trabajo'] ?? null,
                'serie_factura' => $data['serie_factura'] ?? null,
                'numero_factura' => $data['numero_factura'] ?? null,
                'costo_mano_obra' => isset($data['costo_mano_obra']) ? (float) $data['costo_mano_obra'] : null,
                'otros_gastos' => $otrosGastosJson,
                'evidencias' => $evidenciasData ? json_encode($evidenciasData) : null,
                'total_horas' => $activo->total_horas,
                'total_kilometros' => $activo->total_kilometros,
                'total_vueltas' => $activo->total_vueltas,
                'created_at' => now()->toDateTimeString(),
            ];

            $id_mantenimiento = MantenimientoData::crear_mantenimiento($mantenimientoPayload);

            // Recalcular advertencias de mantenimiento del activo
            $updateActivo = [];
            if ($activo->intervalo_mantenimiento_horas > 0) {
                $updateActivo['proxima_advertencia_horas'] = $activo->total_horas + $activo->intervalo_mantenimiento_horas;
            }
            if ($activo->intervalo_mantenimiento_kilometros > 0) {
                $updateActivo['proxima_advertencia_kilometros'] = $activo->total_kilometros + $activo->intervalo_mantenimiento_kilometros;
            }
            if ($activo->intervalo_mantenimiento_vueltas > 0) {
                $updateActivo['proxima_advertencia_vueltas'] = $activo->total_vueltas + $activo->intervalo_mantenimiento_vueltas;
            }

            // Cambiar estado a En Uso si estaba en mantenimiento
            if ($activo->estado === 'En Mantenimiento') {
                $updateActivo['estado'] = 'En Uso';
            }

            if (!empty($updateActivo)) {
                ActivoFijo::where('id', $activo->id)->update($updateActivo);
            }

            // Registrar consumos de insumos
            if (isset($data['productos_consumidos']) && is_array($data['productos_consumidos'])) {
                foreach ($data['productos_consumidos'] as $prod) {
                    $id_detalle = (int) $prod['id_entrega_detalle'];
                    $cantidad_consumida = (float) $prod['cantidad'];
                    $comentario = $prod['comentario'] ?? 'Consumo por mantenimiento';

                    $detalleEntrega = EntregasData::get_entrega_detalle(id_detalle: $id_detalle);
                    if (!$detalleEntrega) {
                        throw new \Exception("El detalle de la entrega {$id_detalle} no existe.");
                    }

                    $cantidad_entregada = (float) $detalleEntrega->cantidad_base;
                    $already_consumed = EntregasData::get_consumido_total_detalle(id_detalle: $id_detalle);
                    $restante = $cantidad_entregada - $already_consumed;

                    if (round($cantidad_consumida, 4) > round($restante, 4)) {
                        throw new \Exception("La cantidad consumida ({$cantidad_consumida}) supera el saldo disponible ({$restante}) del detalle {$id_detalle}.");
                    }

                    $nuevo_total = $already_consumed + $cantidad_consumida;
                    $estadoConsumo = round($nuevo_total, 4) >= round($cantidad_entregada, 4)
                        ? EstadoConsumoDetalleEntregaReq::ConsumoTotal
                        : EstadoConsumoDetalleEntregaReq::ConsumoParcial;

                    RequerimientoAlmacenEntregaDetalleConsumo::crear_consumo(
                        id_requerimiento_almacen_entrega_detalle: $id_detalle,
                        id_empleado_registro: $id_empleado_registro,
                        cantidad_base_consumida: $cantidad_consumida,
                        fecha_hora_consumo: $data['fecha_hora_mantenimiento'],
                        comentario_consumo: $comentario,
                        estado: $estadoConsumo,
                        id_activo_fijo_consumidor: $data['id_activo_fijo'],
                        id_labor_destino: null,
                        id_mantenimiento: $id_mantenimiento,
                        id_lote_mineral: null,
                        para_mantenimiento: true,
                        para_produccion: false
                    );
                }
            }

            return ApiResponse::success([
                'id_mantenimiento' => $id_mantenimiento
            ], 'Mantenimiento registrado correctamente.');
        });
    }
}
