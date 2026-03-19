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
    public static function crear_categoria(
        string $nombre,
        string $tipo_requerimiento,
        ?string $descripcion = null,
        ?string $clasificacion_bien = null,
        bool $es_consumible = false,
        bool $para_cocina = false,
        bool $para_mina = false,
        array $ids_consumidoras = []
    ) {
        if (CategoriasData::verificar_nombre_duplicado($nombre)) {
            return ApiResponse::error('Ya existe una categoría con este nombre.');
        }

        // Validación de negocio: Al menos una clasificación debe ser seleccionada
        if (!$para_cocina && !$para_mina) {
            return ApiResponse::error('Debe seleccionar al menos un área (Mina o Cocina) para la categoría.');
        }

        $id_categoria = CategoriasData::crear_categoria(
            $nombre,
            $tipo_requerimiento,
            $descripcion,
            $clasificacion_bien,
            $es_consumible,
            $para_cocina,
            $para_mina
        );

        // Si es consumible, guardamos sus relaciones
        if ($es_consumible && !empty($ids_consumidoras)) {
            CategoriasData::establecer_consumidoras($id_categoria, $ids_consumidoras);
        }

        $nuevaCategoria = CategoriasData::get_categoria_by_id($id_categoria);

        return ApiResponse::success($nuevaCategoria, 'Categoría creada correctamente');
    }

    /**
     * Actualizar las categorías consumidoras para un insumo existente
     */
    public static function actualizar_consumidoras(int $id_categoria, array $ids_consumidoras)
    {
        // Solo permitimos si la categoría existe y es activa (puedes añadir más validaciones si gustas)
        CategoriasData::establecer_consumidoras($id_categoria, $ids_consumidoras);
        $categoria = CategoriasData::get_categoria_by_id($id_categoria);

        return ApiResponse::success($categoria, 'Destinos de consumo actualizados correctamente');
    }
}
