<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\PrestamoAlmacenRecepcion;
use App\Models\PrestamoAlmacenRecepcionDetalle;
use Illuminate\Support\Facades\DB;

class RecepcionesPrestamoData
{
    /**
     * Crear una cabecera de recepción de préstamo
     */
    public static function crear_recepcion(
        int $id_entrega,
        int $id_empleado,
        string $fecha_hora_recepcion,
        ?string $observacion,
        ?string $evidencias,
        bool $con_incidencia
    ) {
        return PrestamoAlmacenRecepcion::insertGetId([
            'id_prestamo_almacen_entrega' => $id_entrega,
            'id_empleado_registro'        => $id_empleado,
            'observacion'                 => $observacion,
            'fecha_hora_recepcion'        => $fecha_hora_recepcion,
            'evidencias'                  => $evidencias,
            'con_incidencia'              => $con_incidencia ? 1 : 0,
            'created_at'                  => now(),
            'estado'                      => 'Recepcionado',
        ]);
    }

    /**
     * Crear un detalle de recepción de préstamo
     */
    public static function crear_detalle_recepcion(
        int $id_recepcion,
        int $id_entrega_detalle,
        float $cantidad_recepcionada_base
    ) {
        return PrestamoAlmacenRecepcionDetalle::insertGetId([
            'id_prestamo_almacen_recepcion'       => $id_recepcion,
            'id_prestamo_almacen_entrega_detalle' => $id_entrega_detalle,
            'cantidad_recepcionada_base'          => $cantidad_recepcionada_base,
            'estado'                              => 'Recepcionado',
        ]);
    }

    /**
     * Obtener el historial de recepciones de una entrega de préstamo
     */
    public static function get_historial_recepciones(int $id_entrega)
    {
        $sql = '
        SELECT 
            r.id as id_recepcion,
            r.id_prestamo_almacen_entrega,
            r.id_empleado_registro,
            CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
            r.observacion,
            r.fecha_hora_recepcion,
            r.evidencias,
            r.con_incidencia,
            r.created_at,
            r.estado
        FROM 
            prestamo_almacen_recepcion r
        INNER JOIN empleado e ON e.id = r.id_empleado_registro
        WHERE 
            r.id_prestamo_almacen_entrega = :id_entrega
        ORDER BY 
            r.fecha_hora_recepcion DESC;
        ';

        return DB::select($sql, ['id_entrega' => $id_entrega]);
    }

    /**
     * Obtener detalles de una recepción de préstamo
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
            prestamo_almacen_recepcion_detalle rd
        INNER JOIN prestamo_almacen_entrega_detalle ed ON ed.id = rd.id_prestamo_almacen_entrega_detalle
        INNER JOIN prestamo_almacen_detalle pad ON pad.id = ed.id_prestamo_almacen_detalle
        INNER JOIN producto p ON p.id = pad.id_producto
        INNER JOIN unidad_medida ub ON ub.id = p.id_unidad_medida_base
        WHERE 
            rd.id_prestamo_almacen_recepcion = :id_recepcion;
        ';

        return DB::select($sql, ['id_recepcion' => $id_recepcion]);
    }

    /**
     * Actualizar el estado de un detalle de entrega de préstamo basándose en lo recibido
     */
    public static function actualizar_estado_entrega_detalle(int $id_entrega_detalle)
    {
        $detalle = DB::table('prestamo_almacen_entrega_detalle')
            ->where('id', $id_entrega_detalle)
            ->first();

        if (!$detalle) return;

        $recibido = DB::table('prestamo_almacen_recepcion_detalle')
            ->where('id_prestamo_almacen_entrega_detalle', $id_entrega_detalle)
            ->sum('cantidad_recepcionada_base');

        $nuevo_estado = ($recibido >= $detalle->cantidad_base - 0.0001) ? 'Recibido' : 'Recibido Parcialmente';

        DB::table('prestamo_almacen_entrega_detalle')
            ->where('id', $id_entrega_detalle)
            ->update(['estado' => $nuevo_estado]);

        self::actualizar_estado_cabecera_entrega((int)$detalle->id_prestamo_almacen_entrega);
    }

    private static function actualizar_estado_cabecera_entrega(int $id_entrega)
    {
        $detalles = DB::table('prestamo_almacen_entrega_detalle')
            ->where('id_prestamo_almacen_entrega', $id_entrega)
            ->get();

        $todos_recibidos = $detalles->every(fn($d) => $d->estado === 'Recibido');
        $algun_recibido = $detalles->contains(fn($d) => $d->estado === 'Recibido' || $d->estado === 'Recibido Parcialmente');

        $nuevo_estado = $todos_recibidos ? 'Recibida' : ($algun_recibido ? 'Recepcionado Parcialmente' : 'Procesada');

        DB::table('prestamo_almacen_entrega')
            ->where('id', $id_entrega)
            ->update(['estado' => $nuevo_estado]);
    }
}
