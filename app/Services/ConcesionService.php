<?php

namespace App\Modules\Empresas\Services;

use App\Modules\Empresas\Models\Concesion;
use App\Shared\Responses\ApiResponse;

/**
 * Servicio para lógica de negocio del menú de navegación.
 */
class ConcesionService
{
    public function get_concesiones()
    {
        $concesiones = Concesion::get_concesiones();
        return ApiResponse::success($concesiones);
    }

    public function get_concesiones_by_empresa(int $id_empresa)
    {
        $concesiones = Concesion::get_concesiones_by_empresa($id_empresa);
        return ApiResponse::success($concesiones);
    }

    public function get_concesiones_by_usuario(int $id_usuario)
    {
        $concesiones = Concesion::get_concesiones_by_usuario($id_usuario);
        return ApiResponse::success($concesiones);
    }

    public function crear_concesion(string $nombre, ?string $codigo_concesion, ?string $codigo_reinfo, ?string $ubigeo, ?string $tipo_mineral)
    {
        // 1. Verificar nombre duplicado
        if (Concesion::verificar_concesion_existente($nombre)) {
            return ApiResponse::error('Ya existe una concesión con este nombre.');
        }

        // 2. Crear
        $id = Concesion::crear_concesion($nombre, $codigo_concesion, $codigo_reinfo, $ubigeo, $tipo_mineral);

        return ApiResponse::success(Concesion::get_concesion_by_id($id), 'Concesión creada correctamente');
    }

    public function get_empresas_historial(int $id_concesion)
    {
        $asignaciones = Concesion::get_empresas_historial($id_concesion);
        return ApiResponse::success($asignaciones);
    }

    public function asignar_empresa(int $id_concesion, int $id_empresa, string $fecha_inicio, ?string $fecha_fin)
    {
        // Validar si ya está asignada (simple: si está activa)
        if (Concesion::verificar_asignacion_activa($id_concesion, $id_empresa)) {
            return ApiResponse::error('Esta empresa ya está asignada a la concesión actualmente.');
        }

        $id = Concesion::asignar_empresa($id_concesion, $id_empresa, $fecha_inicio, $fecha_fin);
        return ApiResponse::success(['id_asignacion' => $id], 'Empresa asignada correctamente');
    }

    public function desasignar_empresa(int $id_asignacion)
    {
        Concesion::desasignar_empresa($id_asignacion);
        return ApiResponse::success(null, 'Asignación eliminada correctamente');
    }
    public function update_concesion(int $id, string $nombre)
    {
        $concesion = Concesion::get_concesion_by_id($id);
        if (!$concesion) {
            return ApiResponse::error('Concesion no encontrada');
        }

        // verificar si el nombre ya existe en otra concesion (global)
        $existe = Concesion::verificar_concesion_existente($nombre, $id);
        if ($existe) {
            return ApiResponse::error('Ya existe una concesion con el mismo nombre');
        }

        Concesion::update_concesion($id, $nombre);
        return ApiResponse::success(['mensaje' => 'Concesion actualizada correctamente']);
    }

    public function delete_concesion(int $id)
    {
        $concesion = Concesion::get_concesion_by_id($id);
        if (!$concesion) {
            return ApiResponse::error('Concesion no encontrada');
        }

        Concesion::delete_concesion($id);
        return ApiResponse::success(['mensaje' => 'Concesion eliminada correctamente']);
    }
}
