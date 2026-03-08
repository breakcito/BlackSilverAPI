<?php

namespace App\Services;

use App\Models\Categoria;
use App\Shared\Enums\EstadoBase;
use App\Shared\Responses\ApiResponse;

class CategoriasService
{
    public function get_categorias(?string $tipo_requerimiento = null)
    {
        $categorias = Categoria::where('estado', EstadoBase::Activo->value);

        if ($tipo_requerimiento) {
            $categorias->where('tipo_requerimiento', $tipo_requerimiento);
        }

        return ApiResponse::success($categorias->get());
    }

    public function get_categoria_by_id(int $id)
    {
        $categoria = Categoria::find($id);
        if (! $categoria) {
            return ApiResponse::error('Categoria no encontrada');
        }

        return ApiResponse::success($categoria);
    }

    public function crear_categoria(string $nombre, ?string $descripcion, string $tipo_requerimiento, ?string $clasificacion_bien)
    {
        // Validar nombre duplicado
        if (Categoria::where('nombre', $nombre)->where('estado', EstadoBase::Activo->value)->exists()) {
            return ApiResponse::error('Ya existe una categoría con ese nombre');
        }

        $categoria = Categoria::create([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo_requerimiento' => $tipo_requerimiento,
            'clasificacion_bien' => $clasificacion_bien,
            'estado' => EstadoBase::Activo->value,
        ]);

        return ApiResponse::success($categoria);
    }

    public function update_categoria(int $id, string $nombre, ?string $descripcion, string $tipo_requerimiento, ?string $clasificacion_bien)
    {
        $categoria = Categoria::find($id);
        if (! $categoria) {
            return ApiResponse::error('Categoria no encontrada');
        }

        // Validar nombre duplicado excluyendo la categoría actual
        if (Categoria::where('nombre', $nombre)->where('estado', EstadoBase::Activo->value)->where('id', '!=', $id)->exists()) {
            return ApiResponse::error('Ya existe otra categoría con ese nombre');
        }

        $categoria->update([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'tipo_requerimiento' => $tipo_requerimiento,
            'clasificacion_bien' => $clasificacion_bien,
        ]);

        return ApiResponse::success(['mensaje' => 'Categoria actualizada correctamente']);
    }

    public function delete_categoria(int $id)
    {
        $categoria = Categoria::find($id);
        if (! $categoria) {
            return ApiResponse::error('Categoria no encontrada');
        }

        $categoria->update(['estado' => EstadoBase::Inactivo->value]);

        return ApiResponse::success(['mensaje' => 'Categoria eliminada correctamente']);
    }
}
