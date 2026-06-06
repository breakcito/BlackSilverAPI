<?php

namespace App\Modules\ControlConsumoActivos\Data;


use App\Shared\Enums\RequerimientoAlmacen\EstadoConsumoDetalleEntregaReq;
use Illuminate\Support\Facades\DB;

class ControlConsumoData
{
    /**
     * Obtener consumos por detalle(s) de entrega o por ID de consumo específico.
     *
     * @param array<int>|null $id_detalle_entrega
     */
    public static function get_consumos(
        int|array|null $id_detalle_entrega = null,
        ?int $id_consumo = null
    ): array|object|null {
        $sql = '
        SELECT
            c.id as id_consumo,
            c.id_requerimiento_almacen_entrega_detalle,
            c.id_activo_fijo_consumidor,
            c.id_labor_destino,
            c.id_empleado_registro,
            CONCAT(emp.nombre, " ", emp.apellido) as empleado_registro,
            c.cantidad_base_consumida,
            c.fecha_hora_consumo,
            c.comentario_consumo,
            c.created_at,
            c.estado
        FROM requerimiento_almacen_entrega_detalle_consumo c
        LEFT JOIN empleado emp ON emp.id = c.id_empleado_registro
        WHERE 1=1
        ';

        $params = [];

        if ($id_consumo !== null) {
            $sql .= ' AND c.id = :id_consumo';
            $params['id_consumo'] = $id_consumo;
            return DB::selectOne($sql, $params);
        }

        if ($id_detalle_entrega !== null) {
            if (is_array($id_detalle_entrega)) {
                if (empty($id_detalle_entrega)) {
                    return [];
                }
                $ids = array_map('intval', $id_detalle_entrega);
                $sql .= ' AND c.id_requerimiento_almacen_entrega_detalle IN (' . implode(',', $ids) . ')';
            } else {
                $sql .= ' AND c.id_requerimiento_almacen_entrega_detalle = :id_detalle_entrega';
                $params['id_detalle_entrega'] = $id_detalle_entrega;
            }
        }

        $sql .= ' ORDER BY c.fecha_hora_consumo ASC';

        return DB::select($sql, $params);
    }


    /**
     * Registrar un nuevo consumo en la base de datos.
     */
    public static function crear_consumo(
        int $id_requerimiento_almacen_entrega_detalle,
        int $id_empleado_registro,
        float $cantidad_base_consumida,
        string $fecha_hora_consumo,
        ?string $comentario_consumo,
        EstadoConsumoDetalleEntregaReq $estado,
        ?int $id_activo_fijo_consumidor = null,
        ?int $id_labor_destino = null
    ): int {
        return DB::table('requerimiento_almacen_entrega_detalle_consumo')->insertGetId([
            'id_requerimiento_almacen_entrega_detalle' => $id_requerimiento_almacen_entrega_detalle,
            'id_activo_fijo_consumidor' => $id_activo_fijo_consumidor,
            'id_labor_destino' => $id_labor_destino,
            'id_empleado_registro' => $id_empleado_registro,
            'cantidad_base_consumida' => $cantidad_base_consumida,
            'fecha_hora_consumo' => $fecha_hora_consumo,
            'comentario_consumo' => $comentario_consumo,
            'created_at' => now()->toDateTimeString(),
            'estado' => $estado->value,
        ]);
    }
}
