<?php

namespace App\Modules\Categorias;

use App\Shared\Enums\_Generic\TipoBien;
use App\Shared\Enums\_Generic\TipoProducto;
use App\Shared\Responses\ApiResponse;
use App\Modules\Categorias\Data\CategoriasData;
use App\Data\CategoriasData as CategoriasDataGlobal;
use App\Services\CategoriasService as CategoriasServiceGlobal;

class CategoriasService
{
    /**
     * Obtener el listado de categorías activas
     */
    public static function get_categorias()
    {
        $categorias = CategoriasData::get_categorias();

        foreach ($categorias as $categoria) {
            $categoria->categorias_consumidoras = [];
        }

        return ApiResponse::success($categorias);
    }

    /**
     * Crear una nueva categoría
     */
    public static function crear_categoria(
        string $nombre,
        TipoProducto $tipo_producto,
        TipoBien $clasificacion_bien,
        ?string $descripcion = null,
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
        $response = CategoriasServiceGlobal::crear_categoria(
            nombre: $nombre,
            tipo_producto: $tipo_producto,
            clasificacion_bien: $clasificacion_bien,
            descripcion: $descripcion,
            para_transporte: $para_transporte,
            control_por_odometro: $control_por_odometro,
            control_por_horometro: $control_por_horometro,
            control_por_vueltas: $control_por_vueltas,
            es_consumible: $es_consumible,
            para_cocina: $para_cocina,
            para_mina: $para_mina,
            es_auditable: $es_auditable,
            ids_categorias_consumidoras: $ids_categorias_consumidoras,
        );

        if ($response['success'] == false) {
            return $response;
        }

        $id_categoria = $response['data'];
        $nuevaCategoria = CategoriasData::get_categoria_by_id($id_categoria);
        if ($nuevaCategoria) {
            $nuevaCategoria->categorias_consumidoras = [];
        }


        return ApiResponse::success($nuevaCategoria, 'Categoría creada correctamente');
    }

    /**
     * Actualizar las categorías consumidoras para un insumo existente
     */
    public static function actualizar_consumidoras(int $id_categoria, array $ids_categorias_consumidoras)
    {
        // Solo permitimos si la categoría existe y es activa (puedes añadir más validaciones si gustas)
        CategoriasDataGlobal::establecer_consumidoras($id_categoria, []);
        $categoria = CategoriasData::get_categoria_by_id($id_categoria);
        if ($categoria) {
            $categoria->categorias_consumidoras = [];
        }

        return ApiResponse::success($categoria, 'Destinos de consumo actualizados correctamente');
    }
}
