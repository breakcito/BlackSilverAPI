<?php

namespace App\Modules\Categorias;

use App\Shared\Responses\ApiResponse;
use App\Modules\Categorias\Data\CategoriasData;

class CategoriasService
{
    /**
     * Obtener el listado de categorías activas
     */
    public static function get_categorias()
    {
        $categorias = CategoriasData::get_categorias();

        foreach ($categorias as $categoria) {
            self::procesar_categoria($categoria);
        }

        return ApiResponse::success($categorias);
    }

    /**
     * Crear una nueva categoría
     */
    public static function crear_categoria(
        string $nombre,
        string $tipo_producto,
        ?string $descripcion = null,
        ?string $clasificacion_bien = null,
        bool $para_transporte = false,
        bool $control_por_odometro = false,
        bool $control_por_horometro = false,
        bool $control_por_vueltas = false,
        bool $es_consumible = false,
        bool $para_cocina = false,
        bool $para_mina = false,
        bool $es_auditable = false,
        array $ids_categorias_consumidoras = []
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
            $tipo_producto,
            $descripcion,
            $clasificacion_bien,
            $para_transporte,
            $control_por_odometro,
            $control_por_horometro,
            $control_por_vueltas,
            $es_consumible,
            $para_cocina,
            $para_mina,
            $es_auditable
        );

        // Si es consumible, guardamos sus relaciones
        if ($es_consumible && !empty($ids_categorias_consumidoras)) {
            CategoriasData::establecer_consumidoras($id_categoria, $ids_categorias_consumidoras);
        }

        $nuevaCategoria = CategoriasData::get_categoria_by_id($id_categoria);
        self::procesar_categoria($nuevaCategoria);

        return ApiResponse::success($nuevaCategoria, 'Categoría creada correctamente');
    }

    /**
     * Actualizar las categorías consumidoras para un insumo existente
     */
    public static function actualizar_consumidoras(int $id_categoria, array $ids_categorias_consumidoras)
    {
        // Solo permitimos si la categoría existe y es activa (puedes añadir más validaciones si gustas)
        CategoriasData::establecer_consumidoras($id_categoria, $ids_categorias_consumidoras);
        $categoria = CategoriasData::get_categoria_by_id($id_categoria);
        self::procesar_categoria($categoria);

        return ApiResponse::success($categoria, 'Destinos de consumo actualizados correctamente');
    }

    /**
     * Procesa los campos de una categoría (ej: decodifica JSON)
     */
    private static function procesar_categoria($categoria)
    {
        if (!$categoria) return null;
        if (isset($categoria->categorias_consumidoras) && $categoria->categorias_consumidoras) {
            $categoria->categorias_consumidoras = json_decode($categoria->categorias_consumidoras);
        }
        return $categoria;
    }
}
