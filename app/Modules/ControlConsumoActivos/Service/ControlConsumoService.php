<?php

namespace App\Modules\ControlConsumoActivos\Service;

use App\Modules\ControlConsumoActivos\Data\ControlConsumoData;
use App\Shared\Enums\RequerimientoAlmacen\EstadoConsumoDetalleEntregaReq;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class ControlConsumoService
{
    /**
     * Obtener el reporte de consumo de un activo fijo con su respectivo historial agrupado.
     */
    public static function get_reporte(int $id_activo_fijo, int $mes, int $yearcito)
    {
        $detalles = ControlConsumoData::get_reporte(id_activo_fijo: $id_activo_fijo, mes: $mes, yearcito: $yearcito);
        $ids_detalles = array_map(fn($d) => $d->id_entrega_requerimiento_detalle, $detalles);

        $consumos_agrupados = [];
        if (!empty($ids_detalles)) {
            $todos_consumos = ControlConsumoData::get_consumos(id_detalle_entrega: $ids_detalles);
            foreach ($todos_consumos as $c) {
                $consumos_agrupados[$c->id_requerimiento_almacen_entrega_detalle][] = $c;
            }
        }

        foreach ($detalles as $d) {
            $d->consumos = $consumos_agrupados[$d->id_entrega_requerimiento_detalle] ?? [];
        }

        return ApiResponse::success($detalles);
    }

    /**
     * Registrar un nuevo consumo para un detalle de entrega de requerimiento.
     * Valida que no se exceda la cantidad total entregada usando los modelos de Eloquent.
     */
    public static function registrar_consumo(
        int $id_empleado_registro,
        int $id_detalle,
        float $cantidad_base_consumida,
        string $fecha_hora_consumo,
        ?string $comentario_consumo
    ) {
        return DB::transaction(function () use ($id_empleado_registro, $id_detalle, $cantidad_base_consumida, $fecha_hora_consumo, $comentario_consumo) {
            $detalle = ControlConsumoData::get_entrega_detalle(id_detalle: $id_detalle);

            if (!$detalle) {
                return ApiResponse::error('El detalle de la entrega no existe.');
            }

            $cantidad_entregada = (float) $detalle->cantidad_base;
            $already_consumed = ControlConsumoData::get_consumido_total_detalle(id_detalle: $id_detalle);
            $restante = $cantidad_entregada - $already_consumed;

            if (round($cantidad_base_consumida, 4) > round($restante, 4)) {
                return ApiResponse::error("La cantidad a consumir ({$cantidad_base_consumida}) supera la cantidad restante disponible ({$restante}).");
            }

            $nuevo_total_consumido = $already_consumed + $cantidad_base_consumida;

            if (round($nuevo_total_consumido, 4) >= round($cantidad_entregada, 4)) {
                $estado = EstadoConsumoDetalleEntregaReq::ConsumoTotal;
            } else {
                $estado = EstadoConsumoDetalleEntregaReq::ConsumoParcial;
            }

            $id_consumo = ControlConsumoData::crear_consumo(
                id_requerimiento_almacen_entrega_detalle: $id_detalle,
                id_empleado_registro: $id_empleado_registro,
                cantidad_base_consumida: $cantidad_base_consumida,
                fecha_hora_consumo: $fecha_hora_consumo,
                comentario_consumo: $comentario_consumo,
                estado: $estado
            );

            $c = ControlConsumoData::get_consumos(id_consumo: $id_consumo);

            return ApiResponse::success($c);
        });
    }
}
