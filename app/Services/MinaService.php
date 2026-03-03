<?php

namespace App\Services;

use App\Models\Mina;
use App\Shared\Enums\EstadoBase;
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
            'estado' => EstadoBase::Activo->value,
        ]);

        $nuevaMina = Mina::get_minas(id_mina: $mina->id)[0];

        return ApiResponse::success($nuevaMina, 'Mina creada correctamente');
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
            ->update(['estado' => EstadoBase::Inactivo->value]);

        return ApiResponse::success(['mensaje' => 'Mina eliminada correctamente']);
    }
}
