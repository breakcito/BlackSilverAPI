<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Service;

use Illuminate\Support\Facades\DB;
use App\Shared\Responses\ApiResponse;
use App\Shared\Enums\RequerimientoAlmacen\EstadoDetalleRequerimiento;
use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosData;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;
use App\Modules\RequerimientosAlmacenAtencion\Data\SolicitudesData;

class SolicitudService
{
    /**
     * Registrar una solicitud de reabastecimiento (Consulta a Logística).
     */
    public function registrarSolicitudLogistica(
        int $id_requerimiento,
        int $id_empleado,
        string $premura,
        string $fecha_entrega_requerida,
        array $detalles, // {id_requerimiento_almacen_detalle, id_producto, id_unidad_medida, cantidad_solicitada, contenido_por_presentacion, cantidad_solicitada_base, comentario}
        ?string $observacion = null
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
                $premura,
                $fecha_entrega_requerida,
                $observacion
            );

            $correlativo_requerimiento = RequerimientosData::get_correlativo_by_requerimiento($id_requerimiento);
            // 4. Procesar los detalles
            foreach ($detalles as $item) {
                $id_rad = $item['id_requerimiento_almacen_detalle'];

                // a) Crear el detalle de la solicitud
                $id_srd = SolicitudesData::crear_detalle_solicitud(
                    $id_rad,
                    $id_solicitud,
                    $item['id_producto'],
                    $item['id_unidad_medida'],
                    $item['cantidad_solicitada'],
                    $item['contenido_por_presentacion'],
                    $item['cantidad_solicitada_base'],
                    $item['comentario'] ?? null
                );

                // b) Registrar en trazabilidad DE LA SOLICITUD el inicio (Generada)
                SolicitudesData::insert_detalle_log(
                    (int)$id_srd,
                    $id_empleado,
                    "Solicitud generada a partir del requerimiento de almacén N° {$correlativo_requerimiento->correlativo}",
                    EstadoSolicitudDetalle::EsperandoAprobacion
                );

                // c) Actualizar estado en el detalle del requerimiento de almacén
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
                    EstadoDetalleRequerimiento::ConsultaLogistica->getGlosa($correlativoData['correlativo']),
                    EstadoDetalleRequerimiento::ConsultaLogistica
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
