<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\SolicitudReabastecimiento;

class SolicitudesData
{
    // Obtener una o toda la lista de solicitudes hechas por un usuario
    public static function get_solicitudes(
        ?int $id_solicitud = null,
        ?int $id_empleado = null,
        ?int $mes = null,
        ?int $yearcito = null,
    ) {
        return SolicitudReabastecimiento::get_solicitudes(
            id_solicitud: $id_solicitud,
            id_empleado_solicitante: $id_empleado,
            mes: $mes,
            yearcito: $yearcito
        );
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
        string $premura,
        ?string $observacion = null,
        ?string $fecha_entrega_requerida = null,
    ) {
        return SolicitudReabastecimiento::crear_solicitud(
            id_almacen_solicitante: $id_almacen_solicitante,
            id_empleado_solicitante: $id_empleado_solicitante,
            correlativo: $correlativo,
            numero_correlativo: $numero_correlativo,
            premura: $premura,
            observacion: $observacion,
            fecha_entrega_requerida: $fecha_entrega_requerida,
        );
    }


    // Helper que ayuda a calcular el siguiente correlativo - reseteo anual
    public static function get_nuevo_correlativo(int $id_almacen_solicitante)
    {
        return SolicitudReabastecimiento::get_nuevo_correlativo($id_almacen_solicitante);
    }
}
