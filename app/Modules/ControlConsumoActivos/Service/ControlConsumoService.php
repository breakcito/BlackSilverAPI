<?php

namespace App\Modules\ControlConsumoActivos\Service;

use App\Modules\ControlConsumoActivos\Data\ControlConsumoData;
use App\Modules\ControlConsumoActivos\Data\EntregasData;
use App\Services\LotesProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoConsumoDetalleEntregaReq;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class ControlConsumoService
{
    /**
     * Obtener el reporte de consumo de activos fijos e insumos con su respectivo historial agrupado.
     */
    public static function get_reporte(int $mes, int $yearcito)
    {
        $detalles = EntregasData::get_reporte(mes: $mes, yearcito: $yearcito);
        $ids_detalles = array_map(fn($d) => (int) $d->id_entrega_requerimiento_detalle, $detalles);

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
        ?string $comentario_consumo,
        ?int $id_activo_fijo_consumidor = null,
        ?int $id_labor_destino = null,
        ?int $id_lote_mineral = null,
        bool $para_mantenimiento = false,
        bool $para_produccion = false
    ) {
        return DB::transaction(function () use (
            $id_empleado_registro,
            $id_detalle,
            $cantidad_base_consumida,
            $fecha_hora_consumo,
            $comentario_consumo,
            $id_activo_fijo_consumidor,
            $id_labor_destino,
            $id_lote_mineral,
            $para_mantenimiento,
            $para_produccion
        ) {
            if ($para_mantenimiento && !$id_activo_fijo_consumidor) {
                return ApiResponse::error('El activo fijo es obligatorio para mantenimiento.');
            }

            if ($para_produccion && !$id_lote_mineral) {
                return ApiResponse::error('El lote de mineral es obligatorio para producción.');
            }

            $detalle = EntregasData::get_entrega_detalle(id_detalle: $id_detalle);

            if (!$detalle) {
                return ApiResponse::error('El detalle de la entrega no existe.');
            }

            $cantidad_entregada = (float) $detalle->cantidad_base;
            $already_consumed = EntregasData::get_consumido_total_detalle(id_detalle: $id_detalle);
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
                estado: $estado,
                id_activo_fijo_consumidor: $id_activo_fijo_consumidor,
                id_labor_destino: $id_labor_destino,
                id_mantenimiento: null,
                id_lote_mineral: $id_lote_mineral,
                para_mantenimiento: $para_mantenimiento,
                para_produccion: $para_produccion,
            );

            $c = ControlConsumoData::get_consumos(id_consumo: $id_consumo);

            return ApiResponse::success($c);
        });
    }

    /**
     * Registrar un consumo DIRECTO desde Control de Uso (o desde el boton
     * "Registrar Uso de Combustible"). NO requiere un id_requerimiento previo.
     * Inserta la fila con `es_consumo_directo=true` y `uuid_control_uso_activo`,
     * y ademas ejecuta `update_stock` (origen=Consumo, SALIDA) para descontar
     * del lote y registrar el movimiento en kardex, todo en una sola transaccion.
     */
    public static function registrar_consumo_directo(
        int $id_empleado_registro,
        int $id_activo_fijo_consumidor,
        ?int $id_lote_mineral,
        ?int $id_labor_destino,
        bool $para_mantenimiento,
        bool $para_produccion,
        float $cantidad_base_consumida,
        string $fecha_hora_consumo,
        ?string $comentario_consumo,
        string $uuid_control_uso_activo,
        int $id_producto,
        int $id_almacen,
        int $id_lote_producto,
        int $id_unidad_medida,
        float $contenido_por_presentacion,
        float $cantidad_consumo,
        float $cantidad_base,
        EstadoConsumoDetalleEntregaReq $estado
    ) {
        return DB::transaction(function () use (
            $id_empleado_registro,
            $id_activo_fijo_consumidor,
            $id_lote_mineral,
            $id_labor_destino,
            $para_mantenimiento,
            $para_produccion,
            $cantidad_base_consumida,
            $fecha_hora_consumo,
            $comentario_consumo,
            $uuid_control_uso_activo,
            $id_producto,
            $id_almacen,
            $id_lote_producto,
            $id_unidad_medida,
            $contenido_por_presentacion,
            $cantidad_consumo,
            $cantidad_base,
            $estado
        ) {
            // Insert de la fila de consumo directo. NOTA: ya no se
            // vincula a un `id_control_uso_activo` puntual: el consumo
            // representa al GRUPO UUID completo (varios bloques de
            // horometro pueden compartir un mismo consumo de combustible).
            $idConsumo = ControlConsumoData::crear_consumo_directo(
                id_empleado_registro: $id_empleado_registro,
                id_activo_fijo_consumidor: $id_activo_fijo_consumidor,
                id_lote_mineral: $id_lote_mineral,
                id_labor_destino: $id_labor_destino,
                para_mantenimiento: $para_mantenimiento,
                para_produccion: $para_produccion,
                cantidad_base_consumida: $cantidad_base_consumida,
                comentario_consumo: $comentario_consumo,
                uuid_control_uso_activo: $uuid_control_uso_activo,
                id_producto: $id_producto,
                id_almacen: $id_almacen,
                id_lote_producto: $id_lote_producto,
                id_unidad_medida: $id_unidad_medida,
                contenido_por_presentacion: $contenido_por_presentacion,
                cantidad_consumo: $cantidad_consumo,
                cantidad_base: $cantidad_base,
                estado: $estado,
            );

            // Kardex: SALIDA via update_stock (origen=Consumo).
            $activoInfo = DB::table('activo_fijo')
                ->where('id', $id_activo_fijo_consumidor)
                ->first();
            $productoNombre = DB::table('producto')
                ->where('id', $id_producto)
                ->value('nombre');
            $descripcionKardex = sprintf(
                'Se consumio (%s) en %s - %s',
                $productoNombre ?? 'producto',
                $activoInfo->correlativo ?? 'S/C',
                (string) $id_activo_fijo_consumidor,
            );

            LotesProductosService::update_stock(
                id_lote: $id_lote_producto,
                id_origen: $idConsumo,
                tabla_origen: 'requerimiento_almacen_entrega_detalle_consumo',
                tipo_origen: KardexOrigenMovimiento::Consumo,
                tipo_movimiento: KardexTipoMovimiento::Salida,
                cantidad_movimiento_base: $cantidad_base,
                descripcion: $descripcionKardex,
            );

            $c = ControlConsumoData::get_consumos(id_consumo: $idConsumo);

            return ApiResponse::success($c);
        });
    }
}
