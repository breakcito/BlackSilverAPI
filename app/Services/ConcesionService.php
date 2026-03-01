<?php

namespace App\Services;

use App\Models\Concesion;
use App\Models\ContratoConcesion;
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
        if (Concesion::where('nombre', $nombre)->where('estado', \App\Shared\Enums\EstadoBase::Activo->value)->exists()) {
            return ApiResponse::error('Ya existe una concesión con este nombre.');
        }

        // 2. Crear
        $concesion = Concesion::create([
            'nombre' => $nombre,
            'codigo_concesion' => $codigo_concesion,
            'codigo_reinfo' => $codigo_reinfo,
            'ubigeo' => $ubigeo,
            'tipo_mineral' => $tipo_mineral,
            'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
        ]);

        return ApiResponse::success($concesion, 'Concesión creada correctamente');
    }

    public function get_empresas_historial(int $id_concesion)
    {
        $asignaciones = ContratoConcesion::get_empresas_historial($id_concesion);

        return ApiResponse::success($asignaciones);
    }

    public function asignar_empresa(int $id_concesion, int $id_empresa, string $fecha_inicio, ?string $fecha_fin)
    {
        // Validar si ya está asignada (simple: si está activa)
        if (ContratoConcesion::verificar_asignacion_activa($id_concesion, $id_empresa)) {
            return ApiResponse::error('Esta empresa ya está asignada a la concesión actualmente.');
        }

        $id = ContratoConcesion::asignar_empresa($id_concesion, $id_empresa, $fecha_inicio, $fecha_fin);

        return ApiResponse::success(['id_asignacion' => $id], 'Empresa asignada correctamente');
    }

    public function desasignar_empresa(int $id_asignacion)
    {
        ContratoConcesion::desasignar_empresa($id_asignacion);

        return ApiResponse::success(null, 'Asignación eliminada correctamente');
    }

    public function update_concesion(int $id, string $nombre)
    {
        $concesion = Concesion::find($id);
        if (! $concesion) {
            return ApiResponse::error('Concesion no encontrada');
        }

        // verificar si el nombre ya existe en otra concesion (global)
        $existe = Concesion::where('nombre', $nombre)->where('estado', \App\Shared\Enums\EstadoBase::Activo->value)->where('id', '!=', $id)->exists();
        if ($existe) {
            return ApiResponse::error('Ya existe una concesion con el mismo nombre');
        }

        $concesion->update([
            'nombre' => $nombre,
        ]);

        return ApiResponse::success(['mensaje' => 'Concesion actualizada correctamente']);
    }

    public function delete_concesion(int $id)
    {
        $concesion = Concesion::find($id);
        if (! $concesion) {
            return ApiResponse::error('Concesion no encontrada');
        }

        $concesion->update([
            'estado' => \App\Shared\Enums\EstadoBase::Inactivo->value,
        ]);

        return ApiResponse::success(['mensaje' => 'Concesion eliminada correctamente']);
    }
}
