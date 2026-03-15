<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\SolicitudReabastecimientoDetalle;
use App\Models\SolicitudReabastecimientoDetalleLog;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use Illuminate\Support\Facades\DB;

class SolicitudesDetalleData
{

    // Obtener el detalle de una solicitud
    public static function get_detalles_solicitud(int $id_solicitud_reabastecimiento)
    {
        $sql = '
        SELECT
            srd.id AS id_solicitud_detalle,
            pr.nombre as producto, -- manzana
            uni_p.abreviatura as unidad_medida_base_abreviatura, -- kilo
            uni_s.abreviatura as unidad_medida_solicitud_abreviatura, -- caja
            pr.es_fiscalizado,
            pr.es_perecible,
            srd.cantidad_solicitada, -- 2 cajas
            srd.contenido_por_presentacion, -- 10 kilos
            srd.cantidad_solicitada_base, -- 20 kilos
            srd.cantidad_entregada, -- 1/2 caja
            srd.cantidad_entregada_base, -- 5 kilos
            srd.comentario,
            srd.estado
        FROM
            solicitud_reabastecimiento_detalle srd
        INNER JOIN producto pr ON
            pr.id = srd.id_producto
        INNER JOIN unidad_medida uni_s ON
            uni_s.id = srd.id_unidad_medida
        INNER JOIN unidad_medida uni_p ON
            uni_p.id = pr.id_unidad_medida_base
        WHERE srd.id_solicitud_reabastecimiento = :id_solicitud_reabastecimiento
        ';

        return DB::select($sql, ['id_solicitud_reabastecimiento' => $id_solicitud_reabastecimiento]);
    }

    // Funcion helpder que ayuda a crear un detalle de solicitud
    public static function crear_detalle_solicitud(
        int $id_solicitud,
        int $id_producto,
        int $id_unidad_medida,
        float $cantidad_solicitada,
        float $contenido_por_presentacion,
        float $cantidad_solicitada_base,
        ?string $comentario
    ) {
        return SolicitudReabastecimientoDetalle::insertGetId([
            'id_solicitud_reabastecimiento' => $id_solicitud,
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'cantidad_solicitada' => $cantidad_solicitada,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'cantidad_solicitada_base' => $cantidad_solicitada_base,
            'cantidad_entregada' => 0,
            'cantidad_entregada_base' => 0,
            'comentario' => $comentario,
            'estado' => EstadoSolicitudDetalle::EsperandoAprobacion->value,
        ]);
    }

    // Registrar en trazabilidad el cambio de estado de un detalle de solicitud de reabastecimiento
    public static function insert_detalle_log(
        int $id_solicitud_detalle,
        int $id_empleado,
        string $descripcion,
        string $estado
    ) {
        return SolicitudReabastecimientoDetalleLog::insert([
            'id_solicitud_reabastecimiento_detalle' => $id_solicitud_detalle,
            'id_empleado' => $id_empleado,
            'descripcion' => $descripcion,
            'estado' => $estado,
            'created_at' => now(),
        ]);
    }

    /**
     * Obtiene la trazabilidad de un detalle de solicitud
     */
    public static function get_trazabilidad_by_detalle(int $id_detalle)
    {
        $sql = '
            SELECT DISTINCT
                srd.id AS id_trazabilidad,
                CASE
                    WHEN srd.id_empleado IS NOT NULL THEN (
                        SELECT CONCAT(emp.nombre, " ", emp.apellido)
                        FROM empleado emp
                        WHERE emp.id = srd.id_empleado
                    )
                    ELSE NULL
                END AS empleado,
                srd.descripcion,
                srd.created_at,
                srd.estado
            FROM
                solicitud_reabastecimiento_detalle_log srd
            WHERE
            1=1
        ';

        $params = [];

        if ($id_detalle !== null) {
            $sql .= ' AND srd.id_solicitud_reabastecimiento_detalle = :id_detalle';
            $params['id_detalle'] = $id_detalle;
        }

        $sql .= ' ORDER BY srd.created_at DESC';

        return DB::select($sql, $params);
    }
}
