<?php

namespace App\Modules\Productos\Service;

use App\Shared\Responses\ApiResponse;
use App\Services\ProductosService as ProductosServiceGlobal;
use App\Modules\Productos\Data\ProductosData;

class ProductosService
{
    /**
     * Listar todos los productos del catálogo
     */
    public static function get_productos()
    {
        $productos = ProductosData::get_productos();

        return ApiResponse::success($productos);
    }

    /**
     * Registrar un nuevo producto
     */
    public static function crear_producto(
        int $id_categoria,
        int $id_unidad_medida_base,
        string $nombre,
        bool $es_auditable = false,
        bool $es_perecible = false,
        bool $para_mantenimiento = false,
        float $stock_minimo_base = 0,
        float $costo_promedio_base = 0,
        ?string $prefijo = null,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null
    ) {
        $response = ProductosServiceGlobal::crear_producto(
            id_categoria: $id_categoria,
            id_unidad_medida_base: $id_unidad_medida_base,
            nombre: $nombre,
            es_auditable: $es_auditable,
            es_perecible: $es_perecible,
            para_mantenimiento: $para_mantenimiento,
            stock_minimo_base: $stock_minimo_base,
            costo_promedio_base: $costo_promedio_base,
            prefijo: $prefijo,
            tiempo_espera_vencimiento: $tiempo_espera_vencimiento,
            periodo_espera_vencimiento: $periodo_espera_vencimiento,
            return_object: false
        );

        if ($response['success'] == false) {
            return $response;
        }

        $id = $response['data'];
        $producto = ProductosData::get_productos(id_producto: $id);

        return ApiResponse::success($producto, 'Producto registrado correctamente');
    }
}
