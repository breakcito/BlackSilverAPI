<?php

namespace App\Views\RequerimientosAlmacenAtencion\Service;

use Illuminate\Support\Facades\DB;
use App\Shared\Responses\ApiResponse;
use App\Shared\Enums\RequerimientoAlmacen\EstadoDetalleRequerimiento;
use App\Views\RequerimientosAlmacenAtencion\Data\RequerimientosData;
use App\Views\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;
use App\Views\RequerimientosAlmacenAtencion\Data\SolicitudesData;

class SolicitudService
{
    /**
     * Registrar una solicitud de reabastecimiento (Consulta a Logística).
     */
    public function registrarSolicitudLogistica(
        int $id_requerimiento,
        int $id_empleado,
        ?string $observacion,
        string $premura,
        string $fecha_entrega_requerida,
        array $detalles // {id_requerimiento_almacen_detalle, id_producto, id_unidad_medida, cantidad_solicitada, contenido_por_presentacion, cantidad_solicitada_base, comentario}
    ) {
        return DB::transaction(function () use ($id_requerimiento, $id_empleado, $observacion, $premura, $fecha_entrega_requerida, $detalles) {

            $requerimiento = RequerimientosData::get_almacen_destino_by_requerimiento($id_requerimiento);

            $id_almacen_solicitante = $requerimiento->id_almacen_destino;

            // 2. Generar Correlativo para la Solicitud
            $correlativoData = SolicitudesData::get_nuevo_correlativo($id_almacen_solicitante);

            // 3. Crear cabecera de la solicitud
            $id_solicitud = SolicitudesData::crear_solicitud(
                $id_requerimiento,
                $id_almacen_solicitante,
                $id_empleado,
                $correlativoData['correlativo'],
                $correlativoData['numero_correlativo'],
                $observacion ?? '',
                $premura,
                $fecha_entrega_requerida
            );

            // 4. Procesar los detalles
            foreach ($detalles as $item) {
                $id_rad = $item['id_requerimiento_almacen_detalle'];

                // a) Crear el detalle de la solicitud
                SolicitudesData::crear_detalle_solicitud(
                    $id_rad,
                    $id_solicitud,
                    $item['id_producto'],
                    $item['id_unidad_medida'],
                    $item['cantidad_solicitada'],
                    $item['contenido_por_presentacion'],
                    $item['cantidad_solicitada_base'],
                    $item['comentario'] ?? null
                );

                // b) Actualizar estado en el detalle del requerimiento de almacén
                RequerimientosDetalleData::update_detalle_estado(
                    $id_rad,
                    EstadoDetalleRequerimiento::ConsultaLogistica->value,
                    $id_empleado,
                    $item['comentario'] ?? null
                );

                // c) Registrar en trazabilidad el cambio de estado
                RequerimientosDetalleData::insert_detalle_log(
                    $id_rad,
                    $id_empleado,
                    EstadoDetalleRequerimiento::ConsultaLogistica->getGlosa(),
                    EstadoDetalleRequerimiento::ConsultaLogistica->value
                );
            }

            return ApiResponse::success(
                null,
                "La solicitud N° {$correlativoData['correlativo']} ha sido registrada."
            );
        });
    }

    /**
     * Obtener el historial de solicitudes asociadas a un requerimiento.
     */
    public function obtenerHistorialSolicitudes(int $id_requerimiento)
    {
        $data = SolicitudesData::get_solicitudes(id_requerimiento: $id_requerimiento);

        foreach ($data as $solicitud) {
            $solicitud->detalles = SolicitudesData::get_detalles_solicitud(id_solicitud_reabastecimiento: (int) $solicitud->id_solicitud_reabastecimiento);
        }

        return ApiResponse::success($data);
    }
}
