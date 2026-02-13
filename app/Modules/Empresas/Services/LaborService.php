<?php

namespace App\Modules\Empresas\Services;

use App\Modules\Empresas\Models\Labor;
use App\Shared\Responses\ApiResponse;

class LaborService
{
    public function get_labores(?int $id_empresa_concesion = null)
    {
        $labores = Labor::get_labores($id_empresa_concesion);
        return ApiResponse::success($labores);
    }

    public function get_labor_by_id(int $id)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }
        return ApiResponse::success($labor);
    }

    public function crear_labor(int $id_empresa_concesion, string $nombre, ?string $descripcion, string $tipo_labor, string $tipo_sostenimiento)
    {
        // 1. Verificar nombre duplicado en la misma asignacion
        if (Labor::verificar_labor_existente($id_empresa_concesion, $nombre)) {
            return ApiResponse::error('Ya existe una labor con ese nombre en esta concesión/empresa');
        }

        $id_labor = Labor::crear_labor(
            $id_empresa_concesion,
            $nombre,
            $descripcion,
            $tipo_labor,
            $tipo_sostenimiento
        );
        return ApiResponse::success(Labor::get_labor_by_id($id_labor));
    }

    public function update_labor(int $id, int $id_empresa_concesion, string $nombre, ?string $descripcion, string $tipo_labor, string $tipo_sostenimiento)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }

        // 2. Verificar nombre duplicado (excluyendo la actual)
        if (Labor::verificar_labor_existente($id_empresa_concesion, $nombre, $id)) {
            return ApiResponse::error('Ya existe una labor con ese nombre en esta concesión/empresa');
        }

        Labor::update_labor(
            $id,
            $id_empresa_concesion,
            $nombre,
            $descripcion,
            $tipo_labor,
            $tipo_sostenimiento
        );

        return ApiResponse::success(['mensaje' => 'Labor actualizada correctamente']);
    }

    public function delete_labor(int $id)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }

        Labor::delete_labor($id);
        return ApiResponse::success(['mensaje' => 'Labor eliminada correctamente']);
    }

    public function asignar_responsable(int $id_labor, int $id_usuario_empresa, string $fecha_inicio, ?string $observacion)
    {
        // 1. Verificar si la labor existe
        $labor = Labor::get_labor_by_id($id_labor);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }

        // 2. Cerrar responsable activo si existe (Regla de negocio: 1 responsable activo)
        // Se cierra con la fecha de inicio del nuevo responsable (o el día anterior, depende la regla. Usaré fecha_inicio)
        Labor::cerrar_responsable_activo($id_labor, $fecha_inicio);

        // 3. Crear nuevo responsable
        Labor::asignar_responsable($id_labor, $id_usuario_empresa, $fecha_inicio, null, $observacion);

        return ApiResponse::success(['mensaje' => 'Responsable asignado correctamente']);
    }

    public function get_responsables(int $id_labor)
    {
        $responsables = Labor::get_responsables_historial($id_labor);
        return ApiResponse::success($responsables);
    }
}
