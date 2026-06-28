<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Data;

use App\Models\PrestamoAlmacenEntrega;
use App\Models\SolicitudReabastecimientoEntrega;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudEntrega;
use App\Shared\Helpers\CorrelativoHelper;

class EntregasData
{

    /**
     * Obtener el historial de entregas en base a una solicitud
     */
    public static function get_historial_entregas_logistica(?int $id_solicitud = null, ?int $id_entrega = null)
    {
        return SolicitudReabastecimientoEntrega::get_entregas(
            id_entrega: $id_entrega,
            id_solicitud: $id_solicitud
        );
    }

    // Obtener el historial de entregas por prestamo para una solicitud
    public static function get_historial_entregas_prestamo(int $id_solicitud)
    {
        return PrestamoAlmacenEntrega::get_entregas(
            id_solicitud_reabastecimiento: $id_solicitud
        );
    }

    /**
     * Obtener el nuevo correlativo para un entrega en
     * base al almacen que entrega
     */
    public static function get_nuevo_correlativo()
    {
        return CorrelativoHelper::generar(
            prefijo: 'ENT',
            tabla: 'solicitud_reabastecimiento_entrega',
        );
    }

    /**
     * Crear una nueva entrega
     */
    public static function crear_entrega(
        int $id_solicitud,
        int $id_almacen_entrega,
        int $id_empleado_entrega,
        ?int $id_empleado_recibe,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_entrega,
        ?string $observacion = null,
        ?array $evidencias = null,
        ?string $medio_entrega = null,
        ?int $id_proveedor_transporte = null,
        ?int $id_agencia_transporte = null,
        ?string $numero_factura = null,
        ?string $serie_factura = null,
        ?string $serie_guia_transportista = null,
        ?string $numero_guia_transportista = null,
        ?string $serie_guia_remitente = null,
        ?string $numero_guia_remitente = null,
        ?float $costo_envio = null
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
            'medio_entrega' => $medio_entrega,
            'id_proveedor_transporte' => $id_proveedor_transporte,
            'id_agencia_transporte' => $id_agencia_transporte,
            'numero_factura' => $numero_factura,
            'serie_factura' => $serie_factura,
            'serie_guia_transportista' => $serie_guia_transportista,
            'numero_guia_transportista' => $numero_guia_transportista,
            'serie_guia_remitente' => $serie_guia_remitente,
            'numero_guia_remitente' => $numero_guia_remitente,
            'costo_envio' => $costo_envio,
            'created_at' => now(),
            'estado' => EstadoSolicitudEntrega::EnDespacho->value
        ]);
    }
}
