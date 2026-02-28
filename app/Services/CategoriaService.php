<?php

namespace App\Modules\Inventario\Services;

use App\Modules\Inventario\Models\Categoria;
use App\Shared\Responses\ApiResponse;

class CategoriaService
{
    public function get_categorias(?string $tipo_requerimiento = null)
    {
        $categorias = Categoria::get_categorias($tipo_requerimiento);
        return ApiResponse::success($categorias);
    }

    public function get_categoria_by_id(int $id)
    {
        $categoria = Categoria::get_categoria_by_id($id);
        if (!$categoria) {
            return ApiResponse::error('Categoria no encontrada');
        }
        return ApiResponse::success($categoria);
    }

    public function crear_categoria(string $nombre, ?string $descripcion, string $tipo_requerimiento, ?string $clasificacion_bien)
    {
        // Validar nombre duplicado
        if (Categoria::verificar_categoria_existente($nombre)) {
            return ApiResponse::error('Ya existe una categoría con ese nombre');
        }

        $id_categoria = Categoria::crear_categoria(
            $nombre,
            $descripcion,
            $tipo_requerimiento,
            $clasificacion_bien
        );
        return ApiResponse::success(Categoria::get_categoria_by_id($id_categoria));
    }

    public function update_categoria(int $id, string $nombre, ?string $descripcion, string $tipo_requerimiento, ?string $clasificacion_bien)
    {
        $categoria = Categoria::get_categoria_by_id($id);
        if (!$categoria) {
            return ApiResponse::error('Categoria no encontrada');
        }

        // Validar nombre duplicado excluyendo la categoría actual
        if (Categoria::verificar_categoria_existente($nombre, $id)) {
            return ApiResponse::error('Ya existe otra categoría con ese nombre');
        }

        Categoria::update_categoria(
            $id,
            $nombre,
            $descripcion,
            $tipo_requerimiento,
            $clasificacion_bien
        );

        return ApiResponse::success(['mensaje' => 'Categoria actualizada correctamente']);
    }

    public function delete_categoria(int $id)
    {
        $categoria = Categoria::get_categoria_by_id($id);
        if (!$categoria) {
            return ApiResponse::error('Categoria no encontrada');
        }

        Categoria::delete_categoria($id);
        return ApiResponse::success(['mensaje' => 'Categoria eliminada correctamente']);
    }
}
