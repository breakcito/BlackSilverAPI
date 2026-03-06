<?php

namespace App\Services;

use App\Models\AlmacenMina;
use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
use App\Models\RequerimientoAlmacenLabor;
use App\Shared\Enums\EstadoDetalleRequerimiento;
use App\Shared\Helpers\CorrelativoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacenService
{
    public function get_requerimientos(
        ?int $id_mina = null,
        ?int $id_almacen_destino = null,
        ?string $estado = null,
        ?string $fecha_inicio = null,
        ?string $fecha_fin = null
    ) {
        $data = RequerimientoAlmacen::get_requerimientos(
            $id_mina,
            $id_almacen_destino,
            $estado,
            $fecha_inicio,
            $fecha_fin
        );

        return ApiResponse::success($data);
    }

    public function crear_requerimiento(
        int $id_empleado_solicitante,
        int $id_mina,
        ?array $id_labores,
        int $id_almacen_destino,
        string $premura,
        ?string $fecha_entrega_requerida,
        array $detalles
    ) {
        return DB::transaction(function () use (
            $id_empleado_solicitante,
            $id_mina,
            $id_labores,
            $id_almacen_destino,
            $premura,
            $fecha_entrega_requerida,
            $detalles
        ) {
            // 1. Generar Correlativo (Reseteo anual basado en fecha de creación)
            $correlativoData = CorrelativoHelper::generar(
                'requerimiento_almacen', 
                'REQ', 
                [], 
                5, 
                \App\Shared\Enums\Periodo::Anual,
                'created_at'
            );
            
            // 2. Crear Cabecera
            $id_requerimiento = RequerimientoAlmacen::insertGetId([
                'id_empleado_solicitante' => $id_empleado_solicitante,
                'id_mina' => $id_mina,
                'id_almacen_destino' => $id_almacen_destino,
                'correlativo' => $correlativoData['correlativo'],
                'numero_correlativo' => $correlativoData['numero_correlativo'],
                'premura' => $premura,
                'fecha_entrega_requerida' => $fecha_entrega_requerida,
                'created_at' => now(),
                'estado' => \App\Shared\Enums\EstadoRequerimiento::Generada->value,
            ]);

            // 2.1. Asociar Labores (M:N)
            if (! empty($id_labores)) {
                $dataLabores = [];
                foreach ($id_labores as $id_labor) {
                    $dataLabores[] = [
                        'id_requerimiento' => $id_requerimiento,
                        'id_labor' => $id_labor,
                    ];
                }
                DB::table('requerimiento_almacen_labor')->insert($dataLabores);
            }

            // 3. Crear Detalle y Logs
            foreach ($detalles as $detalle) {
                $contenido = (float) $detalle['contenido_por_presentacion'];
                $cantidad = (float) $detalle['cantidad_solicitada'];
                $cantidad_base = $cantidad * $contenido;

                // Insertar detalle
                $id_detalle = RequerimientoAlmacenDetalle::insertGetId([
                    'id_requerimiento_almacen' => $id_requerimiento,
                    'id_producto' => $detalle['id_producto'],
                    'id_unidad_medida' => $detalle['id_unidad_medida'],
                    'cantidad_solicitada' => $cantidad,
                    'contenido_por_presentacion' => $contenido,
                    'cantidad_solicitada_base' => $cantidad_base,
                    'cantidad_entregada' => 0,
                    'cantidad_entregada_base' => 0,
                    'comentario' => $detalle['comentario'] ?? null,
                    'estado' => EstadoDetalleRequerimiento::Pendiente->value,
                ]);

                // Registrar log inicial (Solicitud)
                RequerimientoAlmacenDetalleLog::insert([
                    'id_requerimiento_almacen_detalle' => (int) $id_detalle,
                    'id_empleado' => $id_empleado_solicitante,
                    'tipo_origen' => 'Solicitud',
                    'descripcion' => EstadoDetalleRequerimiento::Pendiente->getGlosa(),
                    'estado' => EstadoDetalleRequerimiento::Pendiente->value,
                    'created_at' => now(),
                ]);
            }

            return ApiResponse::success(
                RequerimientoAlmacen::get_requerimiento_by_id($id_requerimiento),
                'Requerimiento generado correctamente'
            );
        });
    }

    public function get_requerimiento_por_id(int $id)
    {
        $data = RequerimientoAlmacen::get_requerimiento_by_id($id);

        if (! $data) {
            return ApiResponse::error('Requerimiento no encontrado');
        }

        // 1. Obtener Labores
        $data->labores = RequerimientoAlmacenLabor::get_labores_por_requerimiento($id);

        // 2. Obtener Detalles (Productos)
        $data->detalles = RequerimientoAlmacenDetalle::get_detalles_by_requerimiento($id);

        return ApiResponse::success($data);
    }

    /**
     * Lista requerimientos filtrados por almacén de destino (Atención).
     */
    public function obtener_requerimientos_atencion(int $id_almacen, ?string $estado = null, ?string $mes = null, ?string $anio = null)
    {
        $sql = "
        SELECT
            ra.id AS id_requerimiento,
            ra.id_empleado_solicitante,
            CONCAT(emp.nombre, ' ', emp.apellido) AS solicitante,
            ra.id_mina,
            m.nombre AS mina,
            ra.correlativo AS codigo_requerimiento,
            ra.premura,
            ra.fecha_entrega_requerida,
            ra.estado,
            ra.created_at,
            (SELECT COUNT(*) FROM requerimiento_almacen_detalle rad WHERE rad.id_requerimiento_almacen = ra.id) as total_items
        FROM
            requerimiento_almacen ra
        INNER JOIN empleado emp ON emp.id = ra.id_empleado_solicitante
        INNER JOIN mina m ON m.id = ra.id_mina
        WHERE
            ra.id_almacen_destino = :id_almacen
        ";

        $params = [
            'id_almacen' => $id_almacen,
        ];

        if ($estado) {
            $sql .= ' AND ra.estado = :estado';
            $params['estado'] = $estado;
        }

        if ($mes && $anio) {
            $sql .= ' AND MONTH(ra.created_at) = :mes AND YEAR(ra.created_at) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($mes) {
            $sql .= ' AND MONTH(ra.created_at) = :mes';
            $params['mes'] = $mes;
        } elseif ($anio) {
            $sql .= ' AND YEAR(ra.created_at) = :anio';
            $params['anio'] = $anio;
        }

        $sql .= " ORDER BY 
            CASE ra.premura 
                WHEN 'Emergencia' THEN 1 
                WHEN 'Urgente' THEN 2 
                WHEN 'Normal' THEN 3 
                ELSE 4 
            END ASC,
            ra.fecha_entrega_requerida ASC,
            ra.created_at ASC";

        $data = DB::select($sql, $params);

        return ApiResponse::success($data);
    }

    /**
     * Cambia el estado de un producto (Aprobado/Rechazado) y registra en Timeline.
     */
    public function cambiar_estado_detalle(int $id_empleado, int $id_detalle, string $nuevo_estado, ?string $comentario_decision = null)
    {
        return DB::transaction(function () use ($id_empleado, $id_detalle, $nuevo_estado, $comentario_decision) {

            $updateData = [
                'estado' => $nuevo_estado,
                'id_empleado_atencion' => $id_empleado
            ];

            if ($comentario_decision !== null) {
                $updateData['comentario_decision'] = $comentario_decision;
            }

            RequerimientoAlmacenDetalle::where('id', $id_detalle)->update($updateData);

            // Determinar el Enum para el log
            $estadoEnum = EstadoDetalleRequerimiento::from($nuevo_estado);

            RequerimientoAlmacenDetalleLog::insert([
                'id_requerimiento_almacen_detalle' => $id_detalle,
                'id_empleado' => $id_empleado,
                'tipo_origen' => 'Atención',
                'descripcion' => $estadoEnum->getGlosa($comentario_decision),
                'estado' => $estadoEnum->value,
                'created_at' => now(),
            ]);

            return ApiResponse::success(['mensaje' => 'Estado del producto actualizado correctamente']);
        });
    }

    public function get_trazabilidad_detalle(int $id_detalle)
    {
        $data = RequerimientoAlmacenDetalleLog::get_trazabilidad($id_detalle);

        return ApiResponse::success($data);
    }

    public function get_almacenes_por_mina(int $id_mina)
    {
        $data = AlmacenMina::get_almacenes_por_mina($id_mina);

        return ApiResponse::success($data);
    }

    /**
     * Obtiene los productos de un requerimiento listos para ser atendidos,
     * incluyendo los lotes disponibles en el almacén de destino.
     */
    public function get_detalles_para_atencion(int $id_requerimiento)
    {
        $requerimiento = RequerimientoAlmacen::find($id_requerimiento);
        if (!$requerimiento) return ApiResponse::error("Requerimiento no encontrado");

        // Obtenemos los detalles que están aprobados o en proceso de despacho
        $detalles = DB::select("
            SELECT 
                rad.id AS id_requerimiento_detalle,
                rad.id_producto,
                p.nombre AS producto,
                p.es_perecible,
                p.dias_espera_vencimiento,
                um.nombre AS unidad_medida,
                umb.nombre AS unidad_medida_base,
                rad.cantidad_solicitada,
                rad.cantidad_solicitada_base,
                rad.cantidad_entregada_base,
                (rad.cantidad_solicitada_base - rad.cantidad_entregada_base) AS pendiente_base,
                rad.estado
            FROM requerimiento_almacen_detalle rad
            INNER JOIN producto p ON p.id = rad.id_producto
            INNER JOIN unidad_medida um ON um.id = rad.id_unidad_medida
            LEFT JOIN unidad_medida umb ON umb.id = p.id_unidad_medida_base
            WHERE rad.id_requerimiento_almacen = :id_req
            AND rad.estado NOT IN ('Rechazado - Logística', 'Anulada', 'Cerrado', 'Completado')
        ", ['id_req' => $id_requerimiento]);

        foreach ($detalles as $det) {
            // Por cada producto, buscamos sus lotes en el almacén de destino del requerimiento
            $det->lotes = DB::select("
                SELECT 
                    lp.id AS id_lote_producto,
                    lp.correlativo AS codigo_lote,
                    lp.stock_actual,
                    um.abreviatura AS unidad_lote,
                    lp.stock_actual_base,
                    umb.abreviatura AS unidad_base,
                    lp.fecha_vencimiento,
                    lp.contenido_por_presentacion,
                    CONCAT(FORMAT(lp.stock_actual, 2), ' ', um.abreviatura, ' (', FORMAT(lp.stock_actual_base, 2), ' ', umb.abreviatura, ')') AS stock_formateado
                FROM lote_producto lp
                INNER JOIN unidad_medida um ON um.id = lp.id_unidad_medida
                INNER JOIN producto p ON p.id = lp.id_producto
                INNER JOIN unidad_medida umb ON umb.id = p.id_unidad_medida_base
                WHERE lp.id_producto = :id_prod
                AND lp.id_almacen = :id_alm
                AND lp.stock_actual_base > 0
                AND lp.estado = 'Activo'
                ORDER BY lp.fecha_vencimiento ASC, lp.created_at ASC
            ", [
                'id_prod' => $det->id_producto,
                'id_alm' => $requerimiento->id_almacen_destino
            ]);
        }

        return ApiResponse::success($detalles);
    }
}
