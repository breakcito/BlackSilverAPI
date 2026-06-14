<?php

namespace App\Modules\ControlConsumoActivos\Data;


use App\Models\RequerimientoAlmacenEntregaDetalleConsumo;
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
            act.correlativo as correlativo_activo_fijo_consumidor,
            c.id_labor_destino,
            c.id_empleado_registro,
            CONCAT(emp.nombre, " ", emp.apellido) as empleado_registro,
            c.cantidad_base_consumida,
            c.fecha_hora_consumo,
            c.comentario_consumo,
            c.created_at,
            c.estado,
            c.id_mantenimiento,
            c.id_lote_mineral,
            c.para_mantenimiento,
            c.para_produccion,
            lm.correlativo as correlativo_lote_mineral,
            (
                SELECT GROUP_CONCAT(lab.nombre SEPARATOR ", ")
                FROM requerimiento_almacen_entrega_detalle_consumo_labor cl
                INNER JOIN labor lab ON lab.id = cl.id_labor
                WHERE cl.id_requerimiento_almacen_entrega_detalle_consumo = c.id
            ) as labores_destinos,
            (
                SELECT GROUP_CONCAT(cl.id_labor)
                FROM requerimiento_almacen_entrega_detalle_consumo_labor cl
                WHERE cl.id_requerimiento_almacen_entrega_detalle_consumo = c.id
            ) as id_labores
        FROM requerimiento_almacen_entrega_detalle_consumo c
        LEFT JOIN empleado emp ON emp.id = c.id_empleado_registro
        LEFT JOIN lote_mineral lm ON lm.id = c.id_lote_mineral
        LEFT JOIN activo_fijo act ON act.id = c.id_activo_fijo_consumidor
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
        ?int $id_labor_destino = null,
        ?int $id_mantenimiento = null,
        ?int $id_lote_mineral = null,
        bool $para_mantenimiento = false,
        bool $para_produccion = false
    ): int {
        return RequerimientoAlmacenEntregaDetalleConsumo::crear_consumo(
            id_requerimiento_almacen_entrega_detalle: $id_requerimiento_almacen_entrega_detalle,
            id_empleado_registro: $id_empleado_registro,
            cantidad_base_consumida: $cantidad_base_consumida,
            fecha_hora_consumo: $fecha_hora_consumo,
            comentario_consumo: $comentario_consumo,
            estado: $estado,
            id_activo_fijo_consumidor: $id_activo_fijo_consumidor,
            id_labor_destino: $id_labor_destino,
            id_mantenimiento: $id_mantenimiento,
            id_lote_mineral: $id_lote_mineral,
            para_mantenimiento: $para_mantenimiento,
            para_produccion: $para_produccion,
        );
    }
}
