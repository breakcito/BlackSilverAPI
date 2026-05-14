<?php

namespace App\Modules\Productos\Service;

use App\Shared\Responses\ApiResponse;
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
        ?string $prefijo = null,
        bool $es_auditable,
        bool $es_perecible,
        float $stock_minimo_base = 0,
        float $costo_promedio_base = 0,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null
    ) {
        // 1. Validar nombre único
        if (ProductosData::existe_nombre($nombre)) {
            return ApiResponse::error('Ya existe un producto registrado con este nombre.');
        }

        // 2. Procesar perecibilidad
        if (!$es_perecible) {
            $tiempo_espera_vencimiento = null;
            $periodo_espera_vencimiento = null;
            $dias_espera_vencimiento = null;
        } else {
            if ($tiempo_espera_vencimiento && $periodo_espera_vencimiento) {
                $factor = match ($periodo_espera_vencimiento) {
                    'diario' => 1,
                    'semanal' => 7,
                    'mensual' => 30,
                    'anual' => 365,
                    default => 0,
                };
                $dias_espera_vencimiento = $tiempo_espera_vencimiento * $factor;
            }
        }

        // 3. Crear
        $id_producto = ProductosData::crear_producto(
            $id_categoria,
            $id_unidad_medida_base,
            $nombre,
            $prefijo,
            $es_auditable,
            $es_perecible,
            $stock_minimo_base,
            $costo_promedio_base,
            $tiempo_espera_vencimiento,
            $periodo_espera_vencimiento,
            $dias_espera_vencimiento
        );

        $producto = ProductosData::get_producto_by_id($id_producto);

        return ApiResponse::success($producto, 'Producto registrado correctamente');
    }
}
