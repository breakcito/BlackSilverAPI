<?php

namespace App\Services;

use App\Models\Mina;
use App\Shared\Responses\ApiResponse;

class MinaService
{
    // Listar minas
    public function get_minas(?int $id_mina = null, ?int $id_concesion = null)
    {
        $minas = Mina::get_minas($id_mina, $id_concesion);

        return ApiResponse::success($minas);
    }

    /**
     * Crear mina.
     */
    public function crear_mina(int $id_concesion, string $nombre, ?string $descripcion)
    {
        // 1. Crear
        $mina = Mina::create([
            'id_concesion' => $id_concesion,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
        ]);

        return ApiResponse::success(Mina::get_mina_by_id($mina->id), 'Mina creada correctamente');
    }

    /**
     * Actualizar mina.
     */
    public function update_mina(int $id, int $id_concesion, string $nombre, ?string $descripcion)
    {
        Mina::where('id', $id)
            ->update([
                'id_concesion' => $id_concesion,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
            ]);

        return ApiResponse::success(['mensaje' => 'Mina actualizada correctamente']);
    }

    /**
     * Eliminar mina.
     */
    public function delete_mina(int $id)
    {
        Mina::where('id', $id)
            ->update(['estado' => \App\Shared\Enums\EstadoBase::Inactivo->value]);

        return ApiResponse::success(['mensaje' => 'Mina eliminada correctamente']);
    }
}
