<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\SolicitudReabastecimientoRecepcion;
use App\Models\SolicitudReabastecimientoRecepcionDetalle;
use Illuminate\Support\Facades\DB;

class RecepcionesData
{
    /**
     * Crear una cabecera de recepción logística
     */
    public static function crear_recepcion(
        int $id_entrega,
        int $id_empleado,
        string $fecha_hora_recepcion,
        ?string $observacion,
        ?string $evidencias,
        bool $con_incidencia
    ) {
        return SolicitudReabastecimientoRecepcion::insertGetId([
            'id_solicitud_reabastecimiento_entrega' => $id_entrega,
            'id_empleado_registro'                  => $id_empleado,
            'observacion'                           => $observacion,
            'fecha_hora_recepcion'                  => $fecha_hora_recepcion,
            'evidencias'                            => $evidencias,
            'con_incidencia'                        => $con_incidencia ? 1 : 0,
            'created_at'                            => now(),
            'estado'                                => 'Recepcionado',
        ]);
    }

    /**
     * Crear un detalle de recepción logística
     */
    public static function crear_detalle_recepcion(
        int $id_recepcion,
        int $id_entrega_detalle,
        float $cantidad_recepcionada_base
    ) {
        return SolicitudReabastecimientoRecepcionDetalle::insertGetId([
            'id_solicitud_reabastecimiento_recepcion'       => $id_recepcion,
            'id_solicitud_reabastecimiento_entrega_detalle' => $id_entrega_detalle,
            'cantidad_recepcionada_base'                    => $cantidad_recepcionada_base,
            'estado'                                        => 'Recepcionado',
        ]);
    }

    /**
     * Obtener el historial de recepciones de una entrega logística
     */
    public static function get_historial_recepciones(int $id_entrega)
    {
        $sql = '
        SELECT 
            r.id as id_recepcion,
            r.id_solicitud_reabastecimiento_entrega,
            r.id_empleado_registro,
            CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
            r.observacion,
            r.fecha_hora_recepcion,
            r.evidencias,
            r.con_incidencia,
            r.created_at,
            r.estado
        FROM 
            solicitud_reabastecimiento_recepcion r
        INNER JOIN empleado e ON e.id = r.id_empleado_registro
        WHERE 
            r.id_solicitud_reabastecimiento_entrega = :id_entrega
        ORDER BY 
            r.fecha_hora_recepcion DESC;
        ';

        return DB::select($sql, ['id_entrega' => $id_entrega]);
    }

    /**
     * Obtener detalles de una recepción logística
     */
    public static function get_detalles_recepcion(int $id_recepcion)
    {
        $sql = '
        SELECT 
            rd.id as id_recepcion_detalle,
            p.nombre as producto,
            rd.cantidad_recepcionada_base,
            ub.abreviatura as unidad_medida_base_abv
        FROM 
            solicitud_reabastecimiento_recepcion_detalle rd
        INNER JOIN solicitud_reabastecimiento_entrega_detalle ed ON ed.id = rd.id_solicitud_reabastecimiento_entrega_detalle
        INNER JOIN lote_producto lp ON lp.id = ed.id_lote_producto
        INNER JOIN producto p ON p.id = lp.id_producto
        INNER JOIN unidad_medida ub ON ub.id = p.id_unidad_medida_base
        WHERE 
            rd.id_solicitud_reabastecimiento_recepcion = :id_recepcion;
        ';

        return DB::select($sql, ['id_recepcion' => $id_recepcion]);
    }

    /**
     * Actualizar el estado de un detalle de entrega basándose en lo recibido
     */
    public static function actualizar_estado_entrega_detalle(int $id_entrega_detalle)
    {
        $detalle = DB::table('solicitud_reabastecimiento_entrega_detalle')
            ->where('id', $id_entrega_detalle)
            ->first();

        if (!$detalle) return;

        $recibido = DB::table('solicitud_reabastecimiento_recepcion_detalle')
            ->where('id_solicitud_reabastecimiento_entrega_detalle', $id_entrega_detalle)
            ->sum('cantidad_recepcionada_base');

        $nuevo_estado = ($recibido >= $detalle->cantidad_base - 0.0001) ? 'Recibido' : 'Recibido Parcialmente';

        DB::table('solicitud_reabastecimiento_entrega_detalle')
            ->where('id', $id_entrega_detalle)
            ->update(['estado' => $nuevo_estado]);
            
        // También actualizar el estado de la cabecera de la entrega si todos están recibidos
        self::actualizar_estado_cabecera_entrega((int)$detalle->id_reabastecimiento_entrega);
    }

    private static function actualizar_estado_cabecera_entrega(int $id_entrega)
    {
        $detalles = DB::table('solicitud_reabastecimiento_entrega_detalle')
            ->where('id_reabastecimiento_entrega', $id_entrega)
            ->get();

        $todos_recibidos = $detalles->every(fn($d) => $d->estado === 'Recibido');
        $algun_recibido = $detalles->contains(fn($d) => $d->estado === 'Recibido' || $d->estado === 'Recibido Parcialmente');

        $nuevo_estado = $todos_recibidos ? 'Recibida' : ($algun_recibido ? 'Recepcionado Parcialmente' : 'Procesada');

        DB::table('solicitud_reabastecimiento_entrega')
            ->where('id', $id_entrega)
            ->update(['estado' => $nuevo_estado]);
    }
}
