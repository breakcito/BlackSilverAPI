<?php

namespace App\Views\RequerimientosAlmacenAtencion\Service;

use App\Shared\Enums\RequerimientoAlmacen\EstadoDetalleRequerimiento;
use App\Shared\Responses\ApiResponse;
use App\Views\RequerimientosAlmacenAtencion\Data\EntregasData;
use Illuminate\Support\Facades\DB;

class AtencionService
{
    /**
     * Obtiene los almacenes donde el empleado es responsable
     */
    public function get_almacenes_autorizados(int $id_empleado)
    {
        $data = EntregasData::get_almacenes($id_empleado);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los requerimientos por almacén y periodo
     */
    public function get_requerimientos(int $id_almacen, string $mes, string $yearcito)
    {
        $data = EntregasData::get_resumen_requerimientos($id_almacen, $mes, $yearcito);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los detalles de un requerimiento
     */
    public function get_detalles_requerimiento(int $id_requerimiento)
    {
        $data = EntregasData::get_detalles_by_requerimiento($id_requerimiento);
        return ApiResponse::success($data);
    }

    /**
     * Cambia el estado de un producto (Aprobado/Rechazado) y registra en Timeline.
     */
    public function cambiar_estado_detalle(int $id_empleado, int $id_detalle, string $nuevo_estado, ?string $comentario_decision = null)
    {
        return DB::transaction(function () use ($id_empleado, $id_detalle, $nuevo_estado, $comentario_decision) {
            
            EntregasData::update_detalle_estado($id_detalle, $nuevo_estado, $id_empleado, $comentario_decision);

            // Determinar el Enum para el log
            $estadoEnum = EstadoDetalleRequerimiento::from($nuevo_estado);

            EntregasData::insert_detalle_log(
                $id_detalle,
                $id_empleado,
                $estadoEnum->getGlosa($comentario_decision),
                $estadoEnum->value
            );

            return ApiResponse::success(['mensaje' => 'Estado del producto actualizado correctamente']);
        });
    }
}
