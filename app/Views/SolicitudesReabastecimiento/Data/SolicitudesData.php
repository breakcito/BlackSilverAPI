<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\SolicitudReabastecimiento;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitud;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class SolicitudesData
{
    // Obtener una o toda la lista de solicitudes
    public static function get_solicitudes(
        ?int $id_solicitud = null,
        ?int $mes = null,
        ?int $yearcito = null,
    ) {
        $sql = "
        SELECT
            sr.id AS id_solicitud_reabastecimiento,
            sr.id_almacen_solicitante,
            sr.id_requerimiento_almacen,
            req.correlativo as correlativo_requerimiento,
            alm.nombre AS almacen_solicitante,
            CONCAT(em.nombre, ' ', em.apellido) AS empleado_solicitante,
            sr.correlativo,
            sr.premura,
            sr.fecha_entrega_requerida,
            sr.created_at,
            sr.estado
        FROM
            solicitud_reabastecimiento sr
        INNER JOIN empleado em ON
            em.id = sr.id_empleado_solicitante
        INNER JOIN almacen alm ON
            alm.id = sr.id_almacen_solicitante
        LEFT JOIN requerimiento_almacen req on req.id = sr.id_requerimiento_almacen
        WHERE
            1 = 1
        ";

        $params = [];

        // Si se busca por id, devolvemos solo ese registro
        if ($id_solicitud !== null) {
            $sql .= ' AND sr.id = :id_solicitud_reabastecimiento';
            $params['id_solicitud_reabastecimiento'] = $id_solicitud;
            return DB::selectOne($sql, $params);
        }

        // Por periodo
        if ($mes !== null) {
            $sql .= ' AND MONTH(sr.created_at) = :mes';
            $params['mes'] = $mes;
        }

        if ($yearcito !== null) {
            $sql .= ' AND YEAR(sr.created_at) = :yearcito';
            $params['yearcito'] = $yearcito;
        }

        $sql .= ' ORDER BY sr.created_at DESC';

        return DB::select($sql, $params);
    }

    // Obtener una solicitud
    public static function get_solicitud_by_id(int $id_solicitud)
    {
        return self::get_solicitudes(id_solicitud: $id_solicitud);
    }


    // Funcion helpder que ayuda a crear la cabecera de la solicitud
    public static function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $correlativo,
        int $numero_correlativo,
        string $observacion,
        string $premura,
        string $fecha_entrega_requerida,
    ) {
        return SolicitudReabastecimiento::insertGetId([
            'id_almacen_solicitante' => $id_almacen_solicitante,
            'id_empleado_solicitante' => $id_empleado_solicitante,
            'id_requerimiento_almacen' => null,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'observacion' => $observacion,
            'premura' => $premura,
            'fecha_entrega_requerida' => $fecha_entrega_requerida,
            'created_at' => now(),
            'estado' => EstadoSolicitud::Generada->value,
        ]);
    }


    // Helper que ayuda a calcular el siguiente correlativo - reseteo anual
    public static function get_nuevo_correlativo(int $id_almacen_solicitante)
    {
        return SolicitudReabastecimiento::get_nuevo_correlativo($id_almacen_solicitante);
    }
}
