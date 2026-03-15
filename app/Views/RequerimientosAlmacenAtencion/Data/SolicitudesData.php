<?php

namespace App\Views\RequerimientosAlmacenAtencion\Data;

use App\Models\SolicitudReabastecimiento;
use App\Models\SolicitudReabastecimientoDetalle;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitud;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class SolicitudesData
{
    // Obtener una o toda la lista de solicitudes
    public static function get_solicitudes(
        int $id_requerimiento,
    ) {
        $sql = "
        SELECT
            sr.id AS id_solicitud_reabastecimiento,
            CONCAT(em.nombre, ' ', em.apellido) AS empleado_solicitante,
            sr.correlativo,
            sr.observacion,
            sr.premura,
            sr.fecha_hora_entrega_requerida,
            sr.created_at,
            sr.estado
        FROM
            solicitud_reabastecimiento sr
        INNER JOIN empleado em ON
            em.id = sr.id_empleado_solicitante
        WHERE
            sr.id_requerimiento = :id_requerimiento
        ORDER BY
            sr.created_at DESC
        ";

        return DB::select($sql, ['id_requerimiento' => $id_requerimiento]);
    }

    // Obtener el detalle de una solicitud
    public static function get_detalles_solicitud(int $id_solicitud_reabastecimiento)
    {
        $sql = '
        SELECT
            srd.id AS id_solicitud_detalle,
            srd.id_requerimiento_detalle,
            pr.nombre as producto, -- manzana
            uni_p.abreviatura as unidad_medida_base_abv, -- kilo
            uni_s.abreviatura as unidad_medida_solicitud_abv, -- caja
            srd.cantidad_solicitada, -- 2 cajas
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

    // Funcion helpder que ayuda a crear la cabecera de la solicitud
    public static function crear_solicitud(
        int $id_requerimiento_almacen,
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $correlativo,
        int $numero_correlativo,
        string $observacion,
        string $premura,
        string $fecha_entrega_requerida,
    ) {
        return SolicitudReabastecimiento::insertGetId([
            'id_requerimiento_almacen' => $id_requerimiento_almacen,
            'id_almacen_solicitante' => $id_almacen_solicitante,
            'id_empleado_solicitante' => $id_empleado_solicitante,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'observacion' => $observacion,
            'premura' => $premura,
            'fecha_entrega_requerida' => $fecha_entrega_requerida,
            'created_at' => now(),
            'estado' => EstadoSolicitud::Generada->value,
        ]);
    }

    // Funcion helpder que ayuda a crear un detalle de solicitud
    public static function crear_detalle_solicitud(
        int $id_requerimiento_detalle,
        int $id_solicitud,
        int $id_producto,
        int $id_unidad_medida,
        float $cantidad_solicitada,
        float $contenido_por_presentacion,
        float $cantidad_solicitada_base,
        ?string $comentario = null
    ) {
        return SolicitudReabastecimientoDetalle::insertGetId([
            'id_requerimiento_almacen_detalle' => $id_requerimiento_detalle,
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
        return \App\Models\SolicitudReabastecimientoDetalleLog::insert([
            'id_solicitud_reabastecimiento_detalle' => $id_solicitud_detalle,
            'id_empleado' => $id_empleado,
            'descripcion' => $descripcion,
            'estado' => $estado,
            'created_at' => now(),
        ]);
    }

    // Helper que ayuda a calcular el siguiente correlativo - reseteo anual
    public static function get_nuevo_correlativo(int $id_almacen_solicitante)
    {
        return CorrelativoHelper::generar(
            'solicitud_reabastecimiento',
            'SCR',
            ["id_almacen_solicitante" => $id_almacen_solicitante]
        );
    }
}
