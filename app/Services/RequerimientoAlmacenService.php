<?php

namespace App\Services;

use App\Models\RequerimientoAlmacen;
use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
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
        int $id_usuario_solicitante,
        int $id_mina,
        ?array $id_labores,
        int $id_almacen_destino,
        string $premura,
        ?string $fecha_entrega_requerida,
        array $detalles
    ) {
        return DB::transaction(function () use (
            $id_usuario_solicitante,
            $id_mina,
            $id_labores,
            $id_almacen_destino,
            $premura,
            $fecha_entrega_requerida,
            $detalles
        ) {
            // 1. Generar Correlativo
            $correlativoData = CorrelativoHelper::generar('requerimiento_almacen', 'REQ', [], 5, \App\Shared\Enums\Periodo::Anual);
            $nuevo_numero = $correlativoData['numero_correlativo'];
            $correlativo = $correlativoData['correlativo'];

            // 2. Crear Cabecera
            $id_requerimiento = RequerimientoAlmacen::insertGetId([
                'id_usuario_solicitante' => $id_usuario_solicitante,
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
                // Insertar detalle
                $id_detalle = RequerimientoAlmacenDetalle::insertGetId([
                    'id_requerimiento' => $id_requerimiento,
                    'id_producto' => $detalle['id_producto'],
                    'id_unidad_medida' => $detalle['id_unidad_medida'],
                    'cantidad_solicitada' => $detalle['cantidad_solicitada'],
                    'cantidad_atendida' => 0,
                    'comentario' => $detalle['comentario'] ?? null,
                    'estado' => EstadoDetalleRequerimiento::Pendiente->value,
                ]);

                // Registrar log inicial (Pendiente)
                RequerimientoAlmacenDetalleLog::insert([
                    'id_requerimiento_almacen_detalle' => (int) $id_detalle,
                    'id_usuario' => $id_usuario_solicitante,
                    'glosa' => EstadoDetalleRequerimiento::Pendiente->getGlosa(),
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
            ra.id_usuario_solicitante,
            CONCAT(emp.nombre, ' ', emp.apellido) AS solicitante,
            ra.id_mina,
            m.nombre AS mina,
            CONCAT(ra.correlativo, '-', DATE_FORMAT(ra.created_at, '%y'), '-', LPAD(ra.numero_correlativo, 5, '0')) AS codigo_requerimiento,
            ra.premura,
            ra.fecha_entrega_requerida,
            ra.estado,
            ra.created_at,
            (SELECT COUNT(*) FROM requerimiento_almacen_detalle rad WHERE rad.id_requerimiento = ra.id) as total_items
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
    public function cambiar_estado_detalle(int $id_usuario, int $id_detalle, string $nuevo_estado, ?string $comentario_rechazo = null)
    {
        return DB::transaction(function () use ($id_usuario, $id_detalle, $nuevo_estado, $comentario_rechazo) {

            $updateData = ['estado' => $nuevo_estado];
            if ($comentario_rechazo !== null) {
                $updateData['comentario_rechazo'] = $comentario_rechazo;
            }
            RequerimientoAlmacenDetalle::where('id', $id_detalle)->update($updateData);

            // Determinar el Enum para el log
            $estadoEnum = EstadoDetalleRequerimiento::from($nuevo_estado);

            RequerimientoAlmacenDetalleLog::insert([
                'id_requerimiento_almacen_detalle' => $id_detalle,
                'id_usuario' => $id_usuario,
                'glosa' => $estadoEnum->getGlosa($comentario_rechazo),
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
}
