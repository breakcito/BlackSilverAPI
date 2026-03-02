<?php

namespace App\Services;

use App\Models\ContratoConcesion;
use App\Shared\Enums\EstadoBase;
use App\Shared\Responses\ApiResponse;

/**
 * Servicio para lógica de negocio del menú de navegación.
 */
class ContratoConcesionService
{
    public function get_concesiones_by_empresa(int $id_empresa)
    {
        $concesiones = ContratoConcesion::get_concesiones_by_empresa($id_empresa);

        return ApiResponse::success($concesiones);
    }

    public function get_empresas_historial(int $id_concesion)
    {
        $asignaciones = ContratoConcesion::get_empresas_historial($id_concesion);

        return ApiResponse::success($asignaciones);
    }

    public function asignar_empresa(int $id_concesion, int $id_empresa, string $fecha_inicio, ?string $fecha_fin)
    {
        // Validar si ya está asignada
        if (ContratoConcesion::where('id_concesion', $id_concesion)
            ->where('id_empresa', $id_empresa)
            ->where('estado', EstadoBase::Activo->value)
            ->exists()
        ) {
            return ApiResponse::error('Esta empresa ya está asignada a la concesión actualmente.');
        }

        $id = ContratoConcesion::insertGetId([
            'id_concesion' => $id_concesion,
            'id_empresa' => $id_empresa,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'estado' => EstadoBase::Activo->value,
        ]);

        return ApiResponse::success(['id_asignacion' => $id], 'Empresa asignada correctamente');
    }

    public function desasignar_empresa(int $id_asignacion)
    {
        ContratoConcesion::where('id', $id_asignacion)
            ->update(['estado' => EstadoBase::Inactivo->value]);

        return ApiResponse::success(null, 'Asignación eliminada correctamente');
    }
}
