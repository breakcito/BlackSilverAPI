<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\SolicitudReabastecimientoEntrega;
use App\Models\SolicitudReabastecimientoEntregaDetalle;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoDetalleEntrega;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoEntrega;
use Illuminate\Support\Facades\DB;

class EntregasData
{
    // Obtener el historial de entregas para una solicitud
    public static function get_historial_entregas(int $id_solicitud)
    {
        $sql = '
        SELECT DISTINCT
            ent.id AS id_reabastecimiento_entrega,
            ent.id_almacen_entrega,
            alm.nombre as almacen_entrega,
            CONCAT(emp_ent.nombre," ",emp_ent.apellido) AS empleado_entrega,
            CONCAT(emp_rec.nombre," ",emp_rec.apellido) AS empleado_recibe,
            ent.correlativo,
            ent.fecha_hora_entrega,
            ent.observacion,
            ent.evidencias,
            ent.created_at,
            ent.estado
        FROM
            solicitud_reabastecimiento_entrega ent
        INNER JOIN almacen alm on alm.id = ent.id_almacen_entrega
        LEFT JOIN empleado emp_ent ON
            emp_ent.id = ent.id_empleado_entrega
        LEFT JOIN empleado emp_rec ON
            emp_rec.id = ent.id_empleado_recibe
        WHERE ent.id_solicitud_reabastecimiento = :id_solicitud
        ORDER BY ent.correlativo DESC;
        ';

        return DB::select($sql, ['id_solicitud' => $id_solicitud]);
    }

    // Obtener detalles de una entrega
    public static function get_detalles_entrega(int $id_entrega)
    {
        $sql = "
        SELECT
            red.id AS id_entrega_detalle,
            red.id_reabastecimiento_entrega,
            red.id_solicitud_reabastecimiento_detalle,
            srd.id_unidad_medida as id_unidad_medida_solicitada,
            srd.contenido_por_presentacion as contenido_por_presentacion_solicitado,
            uni_sol.abreviatura as unidad_medida_solicitud_abv,
            lot.id_producto,
            lot.id_unidad_medida,
            lot.correlativo,
            lot.fecha_vencimiento,
            prod.nombre as producto,
            prod.es_perecible,
            prod.id_unidad_medida_base,
            red.cantidad_base,
            red.cantidad_lote,
            red.cantidad_solicitud,
            uni_lot.abreviatura as unidad_lote_abv,
            uni_base.abreviatura as unidad_base_abv,
            red.estado as estado_entrega_detalle,
            COALESCE((
                SELECT SUM(rd.cantidad_recepcionada_base)
                FROM solicitud_reabastecimiento_recepcion_detalle rd
                WHERE rd.id_solicitud_reabastecimiento_entrega_detalle = red.id
            ), 0) as cantidad_recibida_total
        FROM
            solicitud_reabastecimiento_entrega_detalle red
        INNER JOIN solicitud_reabastecimiento_detalle srd ON 
            srd.id = red.id_solicitud_reabastecimiento_detalle
        INNER JOIN lote_producto lot ON
            lot.id = red.id_lote_producto
        INNER JOIN producto prod ON
            prod.id = lot.id_producto
        INNER JOIN unidad_medida uni_sol ON
            uni_sol.id = srd.id_unidad_medida
        INNER JOIN unidad_medida uni_base ON
            uni_base.id = prod.id_unidad_medida_base
        INNER JOIN unidad_medida uni_lot ON
            uni_lot.id = lot.id_unidad_medida
        WHERE red.id_reabastecimiento_entrega = :id_entrega
        ORDER BY lot.correlativo DESC;
        ";

        return DB::select($sql, ['id_entrega' => $id_entrega]);
    }


    // Marcar el detalle de la entrega como recibido
    public static function marcar_entrega_detalle_como_recibido(int $id_entrega_detalle)
    {
        return SolicitudReabastecimientoEntregaDetalle::where('id', $id_entrega_detalle)
            ->update([
                'estado' => EstadoDetalleEntrega::Recibido->value
            ]);
    }

    // Verificar y completar la entrega si corresponde
    public static function verificar_y_completar_entrega(int $id_reabastecimiento_entrega)
    {
        $pendientes = SolicitudReabastecimientoEntregaDetalle::where('id_reabastecimiento_entrega', $id_reabastecimiento_entrega)
            ->where('estado', '!=', EstadoDetalleEntrega::Recibido->value)
            ->where('estado', '!=', EstadoDetalleEntrega::Anulado->value)
            ->count();

        if ($pendientes === 0) {
            SolicitudReabastecimientoEntrega::where('id', $id_reabastecimiento_entrega)
                ->update([
                    'estado' => EstadoEntrega::Recibida->value
                ]);
        }
    }
}
