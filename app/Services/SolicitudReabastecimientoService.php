<?php

namespace App\Services;


class SolicitudReabastecimientoService
{

    public function get_solicitudes(int $id_almacen_solicitante) {}

    public function get_detalles_solicitud(int $id_solicitud_reabastecimiento) {}

    public function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $premura,
        string $observacion,
        string $fecha_hora_entrega_requerida,
        // DETALLES: [{
        // "id_producto", "id_unidad_medida", 
        // "cantidad_solicitada", "contenido_por_presentacion", 
        // "comentario"
        // }]
        array $detalles 
    ) {}
}
