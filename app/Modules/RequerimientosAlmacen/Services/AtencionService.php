<?php

namespace App\Modules\RequerimientosAlmacen\Services;

use App\Modules\RequerimientosAlmacen\Models\EntregaAlmacen;
use App\Modules\RequerimientosAlmacen\Models\EntregaAlmacenDetalle;
use App\Modules\RequerimientosAlmacen\Models\RequerimientoAlmacen;
use App\Modules\RequerimientosAlmacen\Models\RequerimientoAlmacenDetalle;
use App\Modules\RequerimientosAlmacen\Models\RequerimientoAlmacenDetalleLog;
use App\Modules\Inventario\Models\LoteProducto;
use App\Modules\Inventario\Models\KardexProducto;
use App\Shared\Enums\EstadoDetalleRequerimiento;
use App\Shared\Enums\EstadoRequerimiento;
use App\Shared\Enums\CodigoMovimiento;
use App\Shared\Enums\TipoMovimiento;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class AtencionService
{
    /**
     * Lista requerimientos filtrados por almacén de destino (Atención).
     */
    public function obtener_requerimientos_atencion(int $id_almacen, ?string $estado = null)
    {
        $sql = "
        SELECT
            ra.id AS id_requerimiento,
            ra.id_usuario_solicitante,
            CONCAT(emp.nombre, ' ', emp.apellido) AS solicitante,
            ra.id_mina,
            m.nombre AS mina,
            CONCAT(ra.correlativo, '-', DATE_FORMAT(ra.created_at, '%y'), '-', LPAD(ra.numero_correlativo, 5, '0')) AS codigo_requerimiento,
            ra.premura,
            ra.fecha_entrega_requerida,
            ra.estado,
            ra.created_at,
            (SELECT COUNT(*) FROM requerimiento_almacen_detalle rad WHERE rad.id_requerimiento = ra.id) as total_items,
            (SELECT COUNT(*) FROM requerimiento_almacen_detalle rad WHERE rad.id_requerimiento = ra.id AND rad.estado = :estado_pendiente) as items_pendientes
        FROM
            requerimiento_almacen ra
        INNER JOIN usuario u ON u.id = ra.id_usuario_solicitante
        INNER JOIN empleado emp ON emp.id = u.id_empleado
        INNER JOIN mina m ON m.id = ra.id_mina
        WHERE
            ra.id_almacen_destino = :id_almacen
        ";

        $params = [
            'id_almacen' => $id_almacen,
            'estado_pendiente' => EstadoDetalleRequerimiento::Pendiente->value
        ];

        if ($estado) {
            $sql .= ' AND ra.estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY ra.created_at DESC';

        $data = DB::select($sql, $params);
        return ApiResponse::success($data);
    }

    /**
     * Cambia el estado de un producto (Aprobado/Rechazado) y registra en Timeline.
     */
    public function cambiar_estado_detalle(int $id_usuario, int $id_detalle, string $nuevo_estado, ?string $comentario_rechazo = null)
    {
        return DB::transaction(function () use ($id_usuario, $id_detalle, $nuevo_estado, $comentario_rechazo) {
            
            RequerimientoAlmacenDetalle::actualizar_estado($id_detalle, $nuevo_estado, $comentario_rechazo);

            // Determinar el Enum para el log
            $estadoEnum = EstadoDetalleRequerimiento::from($nuevo_estado);

            RequerimientoAlmacenDetalleLog::registrar_log(
                $id_detalle,
                $id_usuario,
                $estadoEnum,
                $comentario_rechazo
            );

            return ApiResponse::success(['mensaje' => 'Estado del producto actualizado correctamente']);
        });
    }

    /**
     * Obtiene los lotes disponibles para un producto en un almacén, con lógica FEFO/FIFO.
     */
    public function obtener_lotes_disponibles(int $id_producto, int $id_almacen)
    {
        $sql = "
        SELECT
            lp.id AS id_lote,
            CONCAT(lp.correlativo, '-', DATE_FORMAT(lp.created_at, '%y'), '-', LPAD(lp.numero_correlativo, 5, '0')) AS codigo_lote,
            lp.descripcion,
            lp.stock_actual,
            um.abreviatura AS unidad_medida,
            lp.fecha_ingreso,
            lp.fecha_vencimiento,
            DATEDIFF(lp.fecha_vencimiento, CURDATE()) AS dias_para_vencer
        FROM
            lote_producto lp
        INNER JOIN unidad_medida um ON um.id = lp.id_unidad_medida
        WHERE
            lp.id_producto = :id_producto
            AND lp.id_almacen = :id_almacen
            AND lp.stock_actual > 0
            AND lp.estado = 'Activo'
        ORDER BY
            lp.fecha_vencimiento ASC,
            lp.fecha_ingreso ASC
        ";

        $data = DB::select($sql, [
            'id_producto' => $id_producto,
            'id_almacen'  => $id_almacen
        ]);

        return ApiResponse::success($data);
    }

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
        return DB::transaction(function () use ($id_usuario, $id_requerimiento, $fecha_entrega, $observacion, $detalles) {
            
            // 0. Obtener info del requerimiento para Glosa de Kardex
            $reqInfo = DB::table('requerimiento_almacen as ra')
                ->join('mina as m', 'm.id', '=', 'ra.id_mina')
                ->where('ra.id', $id_requerimiento)
                ->select(
                    'm.nombre as mina',
                    DB::raw("CONCAT(ra.correlativo, '-', DATE_FORMAT(ra.created_at, '%y'), '-', LPAD(ra.numero_correlativo, 5, '0')) as codigo")
                )
                ->first();

            $glosa_kardex = "Entrega {$reqInfo->codigo} - Mina: {$reqInfo->mina}";

            // 1. Generar Correlativo de Entrega
            $prefijo = 'ENTR';
            $nuevo_numero = CorrelativoHelper::proximoNumero('entrega_almacen', 'numero_correlativo');

            // 2. Crear Cabecera de Entrega
            $id_entrega = EntregaAlmacen::crear_entrega(
                $prefijo,
                $nuevo_numero,
                $id_usuario,
                $id_requerimiento,
                $fecha_entrega,
                $observacion
            );

            foreach ($detalles as $item) {
                $id_detalle_req = $item['id_requerimiento_almacen_detalle'];
                $id_lote = $item['id_lote'];
                $cantidad_a_entregar = $item['cantidad'];

                // 3. Obtener Lote para Kardex y Stock
                $lote = LoteProducto::get_lote_by_id($id_lote);
                if (!$lote || $lote->stock_actual < $cantidad_a_entregar) {
                    throw new \Exception("Stock insuficiente en el lote " . ($lote->codigo_lote ?? $id_lote));
                }

                // 4. Crear Detalle de Entrega
                EntregaAlmacenDetalle::crear_detalle_entrega(
                    $id_entrega,
                    $id_detalle_req,
                    $id_lote,
                    $cantidad_a_entregar
                );

                // 5. Descontar Stock del Lote
                LoteProducto::descontar_stock($id_lote, $cantidad_a_entregar);

                // 6. Registrar Kardex (Salida)
                KardexProducto::crear_movimiento(
                    $id_lote,
                    $id_entrega,
                    CodigoMovimiento::Entrega->value,
                    TipoMovimiento::Salida->value,
                    (float)$lote->stock_actual,
                    (float)$cantidad_a_entregar,
                    (float)($lote->stock_actual - $cantidad_a_entregar),
                    $glosa_kardex
                );

                // 7. Actualizar Requerimiento (Cantidad Atendida)
                RequerimientoAlmacenDetalle::actualizar_cantidad_atendida($id_detalle_req, $cantidad_a_entregar);

                // 8. Actualizar Estado del Detalle
                $detalle_req = DB::table('requerimiento_almacen_detalle')->where('id', $id_detalle_req)->first();
                $nuevo_estado_item = ($detalle_req->cantidad_atendida >= $detalle_req->cantidad_solicitada) 
                    ? EstadoDetalleRequerimiento::Completado 
                    : EstadoDetalleRequerimiento::DespachoIniciado;

                RequerimientoAlmacenDetalle::actualizar_estado($id_detalle_req, $nuevo_estado_item->value);

                // 9. Log de Trazabilidad
                RequerimientoAlmacenDetalleLog::registrar_log(
                    $id_detalle_req,
                    $id_usuario,
                    $nuevo_estado_item,
                    "Se entregaron " . $cantidad_a_entregar . " unidades."
                );
            }

            // 10. Verificar si todo el requerimiento está cerrado
            $pendientes = DB::table('requerimiento_almacen_detalle')
                ->where('id_requerimiento', $id_requerimiento)
                ->where('estado', '!=', EstadoDetalleRequerimiento::Completado->value)
                ->where('estado', '!=', EstadoDetalleRequerimiento::Cerrado->value)
                ->where('estado', '!=', EstadoDetalleRequerimiento::RechazadoLogistica->value)
                ->count();

            if ($pendientes === 0) {
                RequerimientoAlmacen::actualizar_estado($id_requerimiento, EstadoRequerimiento::Cerrada->value);
            }

            return ApiResponse::success(['mensaje' => 'Despacho registrado correctamente', 'id_entrega' => $id_entrega]);
        });
    }
    /**
     * Obtiene el historial de entregas realizadas para un ítem específico de un requerimiento.
     */
    public function obtener_historial_entregas_por_item(int $id_detalle)
    {
        $sql = "
        SELECT 
            ea.id AS id_entrega,
            CONCAT(ea.correlativo, '-', DATE_FORMAT(ea.created_at, '%y'), '-', LPAD(ea.numero_correlativo, 5, '0')) AS codigo_entrega,
            ea.fecha_entrega,
            ead.cantidad,
            CONCAT(emp.nombre, ' ', emp.apellido) AS usuario_entrega
        FROM 
            entrega_almacen_detalle ead
        INNER JOIN entrega_almacen ea ON ea.id = ead.id_entrega_almacen
        INNER JOIN usuario u ON u.id = ea.id_usuario_entrega
        INNER JOIN empleado emp ON emp.id = u.id_empleado
        WHERE 
            ead.id_requerimiento_almacen_detalle = :id_detalle
        ORDER BY 
            ea.fecha_entrega DESC
        ";

        $historial = DB::select($sql, ['id_detalle' => $id_detalle]);

        return ApiResponse::success($historial);
    }

    /**
     * Finaliza manualmente un requerimiento, cerrando todos sus ítems pendientes.
     */
    public function finalizar_requerimiento(int $id_usuario, int $id_requerimiento)
    {
        return DB::transaction(function () use ($id_usuario, $id_requerimiento) {
            
            // 1. Cargar el requerimiento
            $requerimiento = DB::table('requerimiento_almacen')->where('id', $id_requerimiento)->first();
            if (!$requerimiento) {
                return ApiResponse::error('Requerimiento no encontrado', 404);
            }

            // 2. Cerrar todos los detalles que no estén terminados ni rechazados
            $detalles = DB::table('requerimiento_almacen_detalle')
                ->where('id_requerimiento', $id_requerimiento)
                ->whereNotIn('estado', [
                    EstadoDetalleRequerimiento::Completado->value,
                    EstadoDetalleRequerimiento::Cerrado->value,
                    EstadoDetalleRequerimiento::RechazadoLogistica->value
                ])
                ->get();

            foreach ($detalles as $detalle) {
                RequerimientoAlmacenDetalle::actualizar_estado($detalle->id, EstadoDetalleRequerimiento::Cerrado->value);
                
                RequerimientoAlmacenDetalleLog::registrar_log(
                    $detalle->id,
                    $id_usuario,
                    EstadoDetalleRequerimiento::Cerrado
                );
            }

            // 3. Cerrar Cabecera
            RequerimientoAlmacen::actualizar_estado($id_requerimiento, EstadoRequerimiento::Cerrada->value);

            return ApiResponse::success(['mensaje' => 'Requerimiento finalizado correctamente']);
        });
    }

    /**
     * Anula un requerimiento siempre y cuando no se haya tomado ninguna decisión sobre sus ítems.
     */
    public function anular_requerimiento(int $id_usuario, int $id_requerimiento)
    {
        return DB::transaction(function () use ($id_usuario, $id_requerimiento) {
            
            $requerimiento = DB::table('requerimiento_almacen')->where('id', $id_requerimiento)->first();
            if (!$requerimiento) {
                return ApiResponse::error('Requerimiento no encontrado', 404);
            }

            // Verificar si hay alguna "decisión" tomada
            $conDecision = DB::table('requerimiento_almacen_detalle')
                ->where('id_requerimiento', $id_requerimiento)
                ->where('estado', '!=', EstadoDetalleRequerimiento::Pendiente->value)
                ->exists();

            if ($conDecision) {
                return ApiResponse::error('No se puede anular el requerimiento porque ya existen ítems con gestión o despacho iniciado.', 400);
            }

            // Anular cabecera
            RequerimientoAlmacen::actualizar_estado($id_requerimiento, EstadoRequerimiento::Anulada->value);

            // También mandamos a Anulada los detalles para consistencia
            DB::table('requerimiento_almacen_detalle')
                ->where('id_requerimiento', $id_requerimiento)
                ->update(['estado' => EstadoRequerimiento::Anulada->value]);

            return ApiResponse::success(['mensaje' => 'Requerimiento anulado correctamente']);
        });
    }
}
