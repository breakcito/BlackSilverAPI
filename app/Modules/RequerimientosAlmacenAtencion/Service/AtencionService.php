<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Service;

use App\Data\AlmacenesData;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalleLog;
use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosData;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;
use Illuminate\Support\Facades\DB;

class AtencionService
{
    /**
     * Obtiene los almacenes donde el empleado es responsable
     */
    public function get_almacenes_autorizados(int $id_empleado)
    {
        $data = AlmacenesData::get_almacenes(id_responsable: $id_empleado);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los requerimientos por almacén y periodo
     */
    public function get_requerimientos(int $id_almacen, string $mes, string $yearcito)
    {
        $data = RequerimientosData::get_resumen_requerimientos($id_almacen, $mes, $yearcito);

        // Adjuntar labores a cada requerimiento
        foreach ($data as $req) {
            $req->labores = RequerimientosData::get_labores_by_requerimiento((int) $req->id_requerimiento);
        }

        return ApiResponse::success($data);
    }

    /**
     * Obtiene los detalles de un requerimiento
     */
    public function get_detalles_requerimiento(int $id_requerimiento)
    {
        $data = RequerimientosDetalleData::get_detalles_by_requerimiento($id_requerimiento);
        return ApiResponse::success($data);
    }

    /**
     * Cambia el estado de uno o varios productos (Aprobado/Rechazado) y registra en Timeline.
     */
    public function cambiar_estado_detalle(int $id_empleado, array $ids_detalles, string $nuevo_estado, ?string $comentario_decision = null)
    {
        return DB::transaction(function () use ($id_empleado, $ids_detalles, $nuevo_estado, $comentario_decision) {

            foreach ($ids_detalles as $id_detalle) {
                // 1. Actualizar el estado del detalle
                RequerimientosDetalleData::update_detalle_estado((int) $id_detalle, $nuevo_estado, $id_empleado, $comentario_decision);

                // 2. Determinar el Enum para el log
                $estadoEnum = EstadoRequerimientoDetalle::from($nuevo_estado);

                // 3. Colocar en estado de proceso al requerimiento si uno de sus detalles es aprobado o consultado con logistica
                if (EstadoRequerimientoDetalle::Aprobado->value == $nuevo_estado || $nuevo_estado == EstadoRequerimientoDetalle::ConsultaLogistica->value) {
                    $requerimiento = RequerimientosDetalleData::get_id_requerimiento_by_detalle((int) $id_detalle);
                    RequerimientosData::update_requerimiento_estado((int) $requerimiento->id_requerimiento_almacen, $nuevo_estado);
                }

                $descripcion = $estadoEnum->getGlosa();
                RequerimientosDetalleData::insert_detalle_log(
                    (int) $id_detalle,
                    $id_empleado,
                    $comentario_decision ?? $descripcion,
                    EstadoRequerimientoDetalleLog::from($nuevo_estado)
                );
            }

            $mensaje = count($ids_detalles) > 1
                ? 'Estado de los productos actualizado correctamente'
                : 'Estado del producto actualizado correctamente';

            return ApiResponse::success(null, $mensaje);
        });
    }

    /**
     * Obtiene la trazabilidad de un detalle de requerimiento
     */
    public function obtener_trazabilidad(int $id_detalle)
    {
        $data = RequerimientosDetalleData::get_detalle_logs($id_detalle);
        return ApiResponse::success($data);
    }
}
