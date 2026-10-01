<?php

namespace App\Modules\SolicitudesReabastecimiento\Data;

use App\Models\SolicitudReabastecimiento;
use App\Shared\Enums\_Generic\Premura;

class SolicitudesData
{
    /**
     * Obtener una o toda la lista de solicitudes hechas por un usuario
     */
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

    /**
     * Obtener una solicitud
     */
    public static function get_solicitud_by_id(int $id_solicitud)
    {
        return self::get_solicitudes(id_solicitud: $id_solicitud);
    }


    /**
     * Funcion helper que ayuda a crear la cabecera de la solicitud
     */
    public static function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $correlativo,
        int $numero_correlativo,
        Premura $premura,
        bool $es_auditable,
        ?string $observacion = null,
        ?string $fecha_entrega_requerida = null,
        ?string $fecha_solicitud = null,
    ) {
        return SolicitudReabastecimiento::crear_solicitud(
            id_almacen_solicitante: $id_almacen_solicitante,
            id_empleado_solicitante: $id_empleado_solicitante,
            correlativo: $correlativo,
            numero_correlativo: $numero_correlativo,
            premura: $premura,
            observacion: $observacion,
            fecha_entrega_requerida: $fecha_entrega_requerida,
            fecha_solicitud: $fecha_solicitud,
            es_auditable: $es_auditable,
        );
    }


    /**
     * Helper que ayuda a calcular el siguiente correlativo - reseteo anual
     */
    public static function get_nuevo_correlativo()
    {
        return SolicitudReabastecimiento::get_nuevo_correlativo();
    }

    /**
     * Actualiza la cabecera de una solicitud con la lista blanca de campos
     * editables. Solo se aplican los campos que vienen no-null en $campos.
     * Los campos `correlativo`, `numero_correlativo`, `id_almacen_solicitante`,
     * `id_empleado_solicitante`, `id_requerimiento_almacen`, `created_at` y
     * `estado` NO son editables desde aca (el estado cambia por otros flujos).
     */
    public static function update_solicitud_cabecera(int $id_solicitud, array $campos)
    {
        $permitidos = [
            'observacion',
            'premura',
            'fecha_solicitud',
            'fecha_entrega_requerida',
            'es_auditable',
        ];

        $updateData = [];
        foreach ($permitidos as $key) {
            if (array_key_exists($key, $campos) && $campos[$key] !== null) {
                $updateData[$key] = $campos[$key];
            }
        }

        if (empty($updateData)) {
            return 0;
        }

        return SolicitudReabastecimiento::where('id', $id_solicitud)
            ->update($updateData);
    }
}
