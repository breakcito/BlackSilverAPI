<?php

namespace App\Modules\ControlConsumoActivos\Data;

use App\Models\RequerimientoAlmacenEntregaDetalle;
use App\Models\RequerimientoAlmacenEntregaDetalleConsumo;
use App\Shared\Enums\RequerimientoAlmacen\EstadoConsumoDetalleEntregaReq;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use Illuminate\Support\Facades\DB;

class ControlConsumoData
{
    /**
     * Listar todos los detalles de la entrega de requerimientos por activo y periodo
     */
    public static function get_reporte(
        int $id_activo_fijo,
        int $mes,
        int $yearcito
    ): array {
        $sql = '
        SELECT
            entd.id as id_entrega_requerimiento_detalle,
            
            -- de que requerimiento
            rad.id_requerimiento_almacen,
            rq.correlativo as correlativo_requerimiento,
            rq.created_at as fecha_requerimiento,
            rq.es_auditable,
            
            -- quien solicito ese requerimiento
            rq.id_contratista_solicitante,
            CONCAT(ctr.nombre, " ", ctr.apellido) as contratista_solicitante,
            
            -- para que mina se solicito
            rq.id_mina,
            mn.nombre as mina,
            
            -- que almacen atendio
            rq.id_almacen_destino,
            alm.nombre as almacen_destino,
            
            -- el produco que pidio y su unidad base
            pr.nombre as producto,
            pr.id_unidad_medida_base,
            umb.nombre as unidad_medida_base,
            umb.abreviatura as unidad_medida_base_abv,
            
            -- en que unidad de medida hizo el requerimiento
            rad.id_unidad_medida as id_unidad_medida_req,
            umr.nombre as unidad_medida_req,
            umr.abreviatura as unidad_medida_req_abv,
            
            -- cantidades solicitadas segun la unidad base y la unidad del requerimiento
            rad.cantidad_solicitada_base,
            rad.cantidad_solicitada,
            
            -- datos de la entrega
            entd.id_requerimiento_almacen_entrega,
            ent.fecha_hora_entrega,
            
            -- cantidades entregadas segun la unidad base y la unidad del requerimiento
            entd.cantidad_base as cantidad_entregada_base,
            entd.cantidad_requerimiento as cantidad_entregada_req,
            
            -- cantidad consumida
            (
                SELECT
                	COALESCE(SUM(cns.cantidad_base_consumida), 0)
                FROM requerimiento_almacen_entrega_detalle_consumo cns
                WHERE entd.id = cns.id_requerimiento_almacen_entrega_detalle
            ) as cantidad_consumida_base
            
        FROM requerimiento_almacen_entrega_detalle entd
        INNER JOIN requerimiento_almacen_entrega ent on ent.id = entd.id_requerimiento_almacen_entrega
        INNER JOIN requerimiento_almacen_detalle rad on rad.id = entd.id_requerimiento_almacen_detalle
        
        -- para saber lo que pidio
        INNER JOIN producto pr on pr.id = rad.id_producto
        INNER JOIN unidad_medida umb on umb.id = pr.id_unidad_medida_base
        INNER JOIN unidad_medida umr on umr.id = rad.id_unidad_medida
 
        -- para saber quien y de donde se pidio 
        INNER JOIN requerimiento_almacen rq on rq.id = rad.id_requerimiento_almacen
        INNER JOIN empleado ctr on ctr.id = rq.id_contratista_solicitante
        INNER JOIN mina mn on mn.id = rq.id_mina
        INNER JOIN almacen alm on alm.id = rq.id_almacen_destino
 
        WHERE 
            -- que lo que solicito este en despacho o su proceso de 
            -- despacho haya sido terminado o completado
            rad.estado IN (:estado_despacho, :estado_cerrado, :estado_completado) AND
            
            -- filtro por periodo
            MONTH(rq.created_at) = :mes AND
            YEAR(rq.created_at) = :yearcito AND
            
            -- filtrar por activo fijo para saber lo que consumio
            rad.id_activo_fijo_destino = :id_activo_fijo
        ';

        $params = [
            'id_activo_fijo' => $id_activo_fijo,
            'mes' => $mes,
            'yearcito' => $yearcito,
            'estado_despacho' => EstadoRequerimientoDetalle::EnDespacho->value,
            'estado_cerrado' => EstadoRequerimientoDetalle::Cerrado->value,
            'estado_completado' => EstadoRequerimientoDetalle::Completado->value,
        ];

        return DB::select($sql, $params);
    }

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
            c.id,
            c.id_requerimiento_almacen_entrega_detalle,
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
     * Obtener un detalle de entrega de requerimiento por ID.
     */
    public static function get_entrega_detalle(int $id_detalle): ?RequerimientoAlmacenEntregaDetalle
    {
        return RequerimientoAlmacenEntregaDetalle::find($id_detalle);
    }

    /**
     * Obtener la suma total consumida de un detalle de entrega.
     */
    public static function get_consumido_total_detalle(int $id_detalle): float
    {
        return (float) RequerimientoAlmacenEntregaDetalleConsumo::where('id_requerimiento_almacen_entrega_detalle', $id_detalle)
            ->sum('cantidad_base_consumida');
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
        EstadoConsumoDetalleEntregaReq $estado
    ): int {
        return DB::table('requerimiento_almacen_entrega_detalle_consumo')->insertGetId([
            'id_requerimiento_almacen_entrega_detalle' => $id_requerimiento_almacen_entrega_detalle,
            'id_empleado_registro' => $id_empleado_registro,
            'cantidad_base_consumida' => $cantidad_base_consumida,
            'fecha_hora_consumo' => $fecha_hora_consumo,
            'comentario_consumo' => $comentario_consumo,
            'created_at' => now()->toDateTimeString(),
            'estado' => $estado->value,
        ]);
    }
}
