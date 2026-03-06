<?php

namespace App\Services;

use App\Models\Concesion;
use App\Shared\Enums\EstadoBase;
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

    public function get_concesiones_by_usuario(int $id_usuario)
    {
        $concesiones = Concesion::get_concesiones_by_usuario($id_usuario);

        return ApiResponse::success($concesiones);
    }

    public function crear_concesion(string $nombre, ?string $codigo_concesion, ?string $codigo_reinfo, ?string $ubigeo, ?string $tipo_mineral)
    {
        // Verificar nombre duplicado
        if (Concesion::where('nombre', $nombre)->where('estado', EstadoBase::Activo->value)->exists()) {
            return ApiResponse::error('Ya existe una concesión con este nombre.');
        }

        // Crear
        $concesion = Concesion::create([
            'nombre' => $nombre,
            'codigo_concesion' => $codigo_concesion,
            'codigo_reinfo' => $codigo_reinfo,
            'ubigeo' => $ubigeo,
            'tipo_mineral' => $tipo_mineral,
            'estado' => EstadoBase::Activo->value,
        ]);

        $nuevaConcesion = Concesion::get_concesiones($concesion->id)[0];

        return ApiResponse::success($nuevaConcesion, 'Concesión creada correctamente');
    }


    public function update_concesion(int $id, string $nombre)
    {
        $concesion = Concesion::find($id);
        if (! $concesion) {
            return ApiResponse::error('Concesion no encontrada');
        }

        // verificar si el nombre ya existe en otra concesion
        $existe = Concesion::where('nombre', $nombre)->where('estado', EstadoBase::Activo->value)->where('id', '!=', $id)->exists();
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
            'estado' => EstadoBase::Inactivo->value,
        ]);

        return ApiResponse::success(['mensaje' => 'Concesion eliminada correctamente']);
    }
}
