<?php

namespace App\Modules\ControlConsumoActivos\Data;

use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use Illuminate\Support\Facades\DB;

class ControlConsumoData
{
    /**
     * Listar todas las cuentas de usuario con información de empleado y rol
     */
    public static function get_reporte(
        int $id_activo_fijo,
        int $mes,
        int $yearcito
    ): array {
        $sql = '
        SELECT
            rad.id as id_requerimiento_detalle,
            
            -- 
            -- DATOS DEL REQUERIMIENTO
            -- 
            -- de que requerimiento
            rad.id_requerimiento_almacen,
            rq.correlativo as correlativo_requerimiento,
            rq.es_auditable,
            
            -- quien lo solicito
            rq.id_contratista_solicitante,
            CONCAT(ctr.nombre, " ", ctr.apellido) as contratista_solicitante,
            
            -- para que mina se solicito
            rq.id_mina,
            mn.nombre as mina,
            
            -- que almacen atendio
            rq.id_almacen_destino,
            alm.nombre as almacen_destino,
            
            -- 
            -- 
            -- 
            
            -- el produco que pidio
            pr.nombre as producto,
            
            -- cantidades segun la unidad base del producto
            pr.id_unidad_medida_base,
            umb.nombre as unidad_medida_base,
            umb.abreviatura as unidad_medida_base_abv,
            rad.cantidad_solicitada_base,
            rad.cantidad_entregada_base,
            
            -- cantidades segun la unidad del requerimiento
            rad.id_unidad_medida as id_unidad_medida_req,
            umr.nombre as unidad_medida_req,
            umr.abreviatura as unidad_medida_req_abv,
            rad.cantidad_solicitada,
            rad.cantidad_entregada,
            
            -- estado de progreso del detalle del requerimiento
            rad.estado
            
        FROM requerimiento_almacen_detalle rad 
        -- para saber lo que pidio
        INNER JOIN producto pr on pr.id = rad.id_producto
        INNER JOIN unidad_medida umb on umb.id = pr.id_unidad_medida_base
        INNER JOIN unidad_medida umr on umr.id = rad.id_unidad_medida

        -- para saber quien y de donde se pidio 
        INNER JOIN requerimiento_almacen rq on rq.id = rad.id_requerimiento_almacen
        INNER JOIN contratista ctr on ctr.id = rq.id_contratista_solicitante
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
            //
            'estado_despacho' => EstadoRequerimientoDetalle::EnDespacho->value,
            'estado_cerrado' => EstadoRequerimientoDetalle::Cerrado->value,
            'estado_completado' => EstadoRequerimientoDetalle::Completado->value,
        ];

        return DB::select($sql, $params);
    }
}
