<?php

namespace App\Views\Almacenes\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\Almacenes\Data\ResponsablesData;

class ResponsablesService
{
    public function __construct(
        private ResponsablesData $data,
    ) {}

    /**
     * Asignar un nuevo responsable de almacen
     */
    public function nuevo_responsable(int $id_almacen, int $id_empleado, string $fecha_inicio)
    {
        // Finalizar el periodo de actividad de los responsables anteriores
        $this->data->update_fecha_fin_responsabilidad($id_almacen, $fecha_inicio);

        // Crear nuevo usando el id de la tabla empleado
        $id_nuevo_responsable = $this->data->nuevo_responsable($id_almacen, $id_empleado, $fecha_inicio);
        $nuevoResponsable = $this->data->get_responsable_by_id($id_nuevo_responsable);

        return ApiResponse::success($nuevoResponsable, 'Responsable asignado correctamente');
    }

    /**
     * Obtener historial de responsables de un almacen
     */
    public function get_historial_responsables(int $id_almacen)
    {
        $historial = $this->data->get_historial_responsables($id_almacen);
        return ApiResponse::success($historial);
    }

    
    /**
     * Obtener listado de empleados para asignar como responsable de almacen
     */
    public function get_empleados(int $id_almacen)
    {
        $result = $this->data->get_empleados($id_almacen);
        return ApiResponse::success($result);
    }
}
