<?php

namespace App\Services;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
use App\Models\RequerimientoAlmacenEntrega;
use App\Models\RequerimientoAlmacenEntregaDetalle;
use App\Shared\Enums\CodigoMovimiento;
use App\Shared\Enums\EstadoBase;
use App\Shared\Enums\EstadoDetalleRequerimiento;
use App\Shared\Enums\EstadoRequerimiento;
use App\Shared\Enums\Periodo;
use App\Shared\Enums\TipoMovimiento;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;

class RequerimientoAlmacenEntregaService
{

    /**
     * Registra una entrega masiva de productos (Despacho).
     */
    public function registrar_entrega(
        int $id_usuario,
        int $id_requerimiento,
        string $fecha_entrega,
        ?string $observacion,
        array $detalles
    ) {

        // Validar stock
        foreach ($detalles as $item) {
            $id_lote = $item['id_lote'];
            $cantidad_a_entregar = $item['cantidad'];
            $lote = LoteProducto::find($id_lote, ['correlativo', 'stock_actual']);
            if (!$lote || $lote->stock_actual < $cantidad_a_entregar) {
                return ApiResponse::error('Stock insuficiente en el lote ' . ($lote->correlativo ?? $id_lote));
            }
        }

        // Generar Correlativo de Entrega
        $prefijo = 'ENTR';
        $correlativoData = CorrelativoHelper::generar('requerimiento_almacen_entrega', $prefijo, [], 5, Periodo::Anual);

        // Crear Cabecera de Entrega
        $id_entrega = RequerimientoAlmacenEntrega::insertGetId([
            'correlativo' => $correlativoData['correlativo'],
            'numero_correlativo' => $correlativoData['numero_correlativo'],
            'id_empleado_entrega' => $id_usuario,
            'id_requerimiento_almacen' => $id_requerimiento,
            'fecha_hora_entrega' => $fecha_entrega,
            'observacion' => $observacion,
            'evidencias' => null,
            'created_at' => now(),
            'estado' => EstadoRequerimiento::Generada->value,
        ]);

        foreach ($detalles as $item) {
            $id_detalle_req = $item['id_requerimiento_almacen_detalle'];
            $id_lote = $item['id_lote'];
            $cantidad_a_entregar = $item['cantidad'];

            // Obtener Lote para Kardex y Stock
            // Crear Detalle de Entrega
            RequerimientoAlmacenEntregaDetalle::insert([
                'id_requerimiento_almacen_entrega' => $id_entrega,
                'id_requerimiento_almacen_detalle' => $id_detalle_req,
                'id_lote' => $id_lote,
                'cantidad' => $cantidad_a_entregar,
            ]);

            $lote = LoteProducto::find($id_lote, ['stock_actual']);
            LoteProducto::where('id', $id_lote)->decrement('stock_actual', $cantidad_a_entregar);

            // 6. Registrar Kardex (Salida)
            KardexProducto::create([
                'id_lote_producto' => $id_lote,
                'id_cabecera' => $id_entrega,
                'codigo_movimiento' => CodigoMovimiento::Entrega->value,
                'tipo_movimiento' => TipoMovimiento::Salida->value,
                'cantidad_anterior' => (float) $lote->stock_actual,
                'cantidad_movimiento' => (float) $cantidad_a_entregar,
                'cantidad_resultante' => (float) ($lote->stock_actual - $cantidad_a_entregar),
                'glosa' => "",
                'estado' => EstadoBase::Activo->value,
            ]);

            // Obtener estado original antes de incrementar cantidades para saber si es el primer despacho
            $detalle_original = RequerimientoAlmacenDetalle::where('id', $id_detalle_req)->first();

            // Actualizar Requerimiento (Cantidad Atendida)
            RequerimientoAlmacenDetalle::where('id', $id_detalle_req)->increment('cantidad_atendida', $cantidad_a_entregar);

            // Actualizar Estado del Detalle
            $detalle_req = RequerimientoAlmacenDetalle::where('id', $id_detalle_req)->first();
            $nuevo_estado_item = ($detalle_req->cantidad_atendida >= $detalle_req->cantidad_solicitada)
                ? EstadoDetalleRequerimiento::Completado
                : EstadoDetalleRequerimiento::DespachoIniciado;

            RequerimientoAlmacenDetalle::where('id', $id_detalle_req)->update(['estado' => $nuevo_estado_item->value]);

            // Registrar el log de 'Despacho Iniciado' si es la primera entrega física (estaba en Aprobación)
            if ($detalle_original->cantidad_atendida == 0 && $cantidad_a_entregar > 0) {
                RequerimientoAlmacenDetalleLog::insert([
                    'id_requerimiento_almacen_detalle' => $id_detalle_req,
                    'id_usuario' => $id_usuario,
                    'glosa' => EstadoDetalleRequerimiento::DespachoIniciado->getGlosa(),
                    'estado' => EstadoDetalleRequerimiento::DespachoIniciado->value,
                    'created_at' => now(),
                ]);
            }

            // Log de Trazabilidad de la Entrega
            RequerimientoAlmacenDetalleLog::insert([
                'id_requerimiento_almacen_detalle' => $id_detalle_req,
                'id_usuario' => $id_usuario,
                'glosa' => EstadoDetalleRequerimiento::NuevaEntrega->getGlosa((string) $cantidad_a_entregar),
                'estado' => EstadoDetalleRequerimiento::NuevaEntrega->value,
                'created_at' => now(),
            ]);

            // Si con esta entrega se completó lo solicitado, registrar el log de Completado
            if ($nuevo_estado_item === EstadoDetalleRequerimiento::Completado && $detalle_req->estado !== EstadoDetalleRequerimiento::Completado->value) {
                RequerimientoAlmacenDetalleLog::insert([
                    'id_requerimiento_almacen_detalle' => $id_detalle_req,
                    'id_usuario' => $id_usuario,
                    'glosa' => EstadoDetalleRequerimiento::Completado->getGlosa(),
                    'estado' => EstadoDetalleRequerimiento::Completado->value,
                    'created_at' => now(),
                ]);
            }
        }

        // Verificar si todo el requerimiento está cerrado
        $pendientes = RequerimientoAlmacenDetalle::where('id_requerimiento', $id_requerimiento)
            ->where('estado', '!=', EstadoDetalleRequerimiento::Completado->value)
            ->where('estado', '!=', EstadoDetalleRequerimiento::Cerrado->value)
            ->where('estado', '!=', EstadoDetalleRequerimiento::RechazadoLogistica->value)
            ->count();

        if ($pendientes === 0) {
            RequerimientoAlmacen::where('id', $id_requerimiento)->update(['estado' => EstadoRequerimiento::Cerrada->value]);
        }

        return ApiResponse::success(['mensaje' => 'Despacho registrado correctamente', 'id_entrega' => $id_entrega]);
    }

    /**
     * Obtiene el historial de entregas realizadas para un ítem específico de un requerimiento.
     */
    public function obtener_historial_entregas_por_item(int $id_detalle)
    {
        $historial = RequerimientoAlmacenEntrega::get_entregas_by_detalle($id_detalle);
        return ApiResponse::success($historial);
    }
}
