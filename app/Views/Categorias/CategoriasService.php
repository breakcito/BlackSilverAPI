<?php

namespace App\Views\Categorias;

use App\Shared\Responses\ApiResponse;
use App\Views\Categorias\Data\CategoriasData;

class CategoriasService
{
    /**
     * Obtener el listado de categorías activas
     */
    public static function get_categorias()
    {
        $categorias = CategoriasData::get_categorias();

        return ApiResponse::success($categorias);
    }

    /**
     * Crear una nueva categoría
     */
    public static function crear_categoria(array $data)
    {
        if (CategoriasData::verificar_nombre_duplicado($data['nombre'])) {
            return ApiResponse::error('Ya existe una categoría con este nombre.');
        }

        $id_categoria = CategoriasData::crear_categoria($data);
        $nuevaCategoria = CategoriasData::get_categoria_by_id($id_categoria);

        return ApiResponse::success($nuevaCategoria, 'Categoría creada correctamente');
    }
}
