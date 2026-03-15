<?php

namespace App\Views\SolicitudesReabastecimiento\Service;

use App\Shared\Responses\ApiResponse;

class SolicitudesService
{
    
    // Obtener todas la lista de solicitudes en base al almacen solicitante
    // dentro de un periodo de tiempo (mes y año)
    public function get_solicitudes(int $id_almacen_solicitante, int $mes, int $yearcito)
    {
        $data = $this->data->get_solicitudes(
            id_almacen_solicitante: $id_almacen_solicitante,
            mes: $mes,
            yearcito: $yearcito
        );

        return ApiResponse::success($data);
    }

    // Registrar una solicitud y sus detalles
    public function crear_solicitud(
        int $id_almacen_solicitante,
        int $id_empleado_solicitante,
        string $premura,
        ?string $observacion,
        ?string $fecha_entrega_requerida,
        array $detalles
    ) {
        // 1. Generar Correlativo
        $correlativoData = $this->data->get_nuevo_correlativo($id_almacen_solicitante);
        $correlativo = $correlativoData['correlativo'];
        $numero_correlativo = $correlativoData['numero_correlativo'];

        // 2. Crear cabecera
        $id_solicitud = $this->data->crear_solicitud(
            $id_almacen_solicitante,
            $id_empleado_solicitante,
            $correlativo,
            $numero_correlativo,
            $observacion,
            $premura,
            $fecha_entrega_requerida
        );

        // 3. Crear detalles
        foreach ($detalles as $detalle) {
            $id_producto = $detalle['id_producto'];
            $id_unidad_medida = $detalle['id_unidad_medida'];
            $cantidad_solicitada = (float) $detalle['cantidad_solicitada'];
            $contenido_por_presentacion = (float) $detalle['contenido_por_presentacion'];
            $cantidad_solicitada_base = $cantidad_solicitada * $contenido_por_presentacion;
            $comentario = $detalle['comentario'] ?? null;

            $this->data->crear_detalle_solicitud(
                $id_solicitud,
                $id_producto,
                $id_unidad_medida,
                $cantidad_solicitada,
                $contenido_por_presentacion,
                $cantidad_solicitada_base,
                $comentario
            );
        }

        return ApiResponse::success(
            $this->data->get_solicitud_by_id($id_solicitud),
            'Solicitud generada correctamente'
        );
    }

    // Obtener los detalles de una solicitud
    public function get_detalles_solicitud(int $id_solicitud)
    {
        $detalles = $this->data->get_detalles_solicitud($id_solicitud);

        return ApiResponse::success($detalles);
    }

    // Obtener toda la lista de productos junto a la abreviatura de su unidad de medida
    public function get_productos()
    {
        return ApiResponse::success($this->data->get_productos());
    }

    // Obtener la lista de almacenes en las que el empleado
    // solicitante es reesponsable
    public function get_almacenes($id_empleado)
    {
        return ApiResponse::success($this->data->get_almacenes($id_empleado));
    }

    // Listar unidades de medida.
    public function get_unidades_medida()
    {
        return ApiResponse::success($this->data->get_unidades_medida());
    }
}
