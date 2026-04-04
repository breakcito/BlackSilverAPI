<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Data;

use App\Models\SolicitudReabastecimientoEntrega;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoEntrega;
use App\Shared\Helpers\CorrelativoHelper;

class EntregasData
{

    /**
     * Obtener el historial de entregas en base a una solicitud
     */
    public static function get_historial_entregas(?int $id_solicitud = null, ?int $id_entrega = null)
    {
        return SolicitudReabastecimientoEntrega::get_entregas(
            id_entrega: $id_entrega,
            id_solicitud: $id_solicitud
        );
    }

    /**
     * Obtener el nuevo correlativo para un entrega en
     * base al almacen que entrega
     */
    public static function get_nuevo_correlativo(int $id_almacen_entrega)
    {
        return CorrelativoHelper::generar(
            prefijo: 'ENT',
            tabla: 'solicitud_reabastecimiento_entrega',
            filtros: ['id_almacen_entrega' => $id_almacen_entrega],
        );
    }

    /**
     * Crear una nueva entrega
     */
    public static function crear_entrega(
        int $id_solicitud,
        int $id_almacen_entrega,
        int $id_empleado_entrega,
        int $id_empleado_recibe,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_entrega,
        ?string $observacion = null,
        ?array $evidencias = null,
    ) {
        return SolicitudReabastecimientoEntrega::insertGetId([
            'id_solicitud_reabastecimiento' => $id_solicitud,
            'id_almacen_entrega' => $id_almacen_entrega,
            'id_empleado_entrega' => $id_empleado_entrega,
            'id_empleado_recibe' => $id_empleado_recibe,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'fecha_hora_entrega' => $fecha_hora_entrega,
            'observacion' => $observacion,
            'evidencias' => $evidencias ? json_encode($evidencias) : null,
            'created_at' => now(),
            'estado' => EstadoEntrega::Procesada->value
        ]);
    }
}
