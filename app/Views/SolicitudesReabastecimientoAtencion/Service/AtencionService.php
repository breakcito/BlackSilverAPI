<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Service;

use App\Shared\Enums\RequerimientoAlmacen\EstadoDetalleRequerimiento;
use App\Shared\Responses\ApiResponse;
use App\Views\RequerimientosAlmacenAtencion\Data\AuxData;
use App\Views\RequerimientosAlmacenAtencion\Data\RequerimientosData;
use App\Views\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;
use Illuminate\Support\Facades\DB;

class AtencionService
{
    /**
     * Obtiene los almacenes donde el empleado es responsable
     */
    public function get_almacenes_autorizados(int $id_empleado)
    {
        $data = AuxData::get_almacenes($id_empleado);
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
     * Cambia el estado de un producto (Aprobado/Rechazado/Consultar con logistica) y registra en Timeline.
     */
    public function cambiar_estado_detalle(int $id_empleado, int $id_detalle, string $nuevo_estado, ?string $comentario_decision = null)
    {
        return DB::transaction(function () use ($id_empleado, $id_detalle, $nuevo_estado, $comentario_decision) {

            RequerimientosDetalleData::update_detalle_estado($id_detalle, $nuevo_estado, $id_empleado, $comentario_decision);

            // Determinar el Enum para el log
            $estadoEnum = EstadoDetalleRequerimiento::from($nuevo_estado);

            // Colocar en estado de proceso al requerimiento si uno de sus detalles es aprobado o consultado con logistica
            if (EstadoDetalleRequerimiento::Aprobado == $nuevo_estado || $nuevo_estado == EstadoDetalleRequerimiento::ConsultaLogistica) {
                $requerimiento = RequerimientosDetalleData::get_id_requerimiento_by_detalle($id_detalle);
                RequerimientosData::update_requerimiento_estado((int) $requerimiento->id_requerimiento_almacen, $nuevo_estado);
            }
            $descripcion = $estadoEnum->getGlosa($comentario_decision);
            RequerimientosDetalleData::insert_detalle_log(
                $id_detalle,
                $id_empleado,
                $comentario_decision ?? $descripcion,
                $estadoEnum->value
            );

            return ApiResponse::success(null, 'Estado del producto actualizado correctamente');
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
