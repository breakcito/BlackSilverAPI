<?php

namespace App\Services;

use App\Models\Producto;
use App\Shared\Responses\ApiResponse;

class ProductoService
{
    /**
     * Listar todos los productos.
     */
    public function get_productos()
    {
        $productos = Producto::get_productos();

        // Formatear booleans para que el front lo reciba limpio
        $productos = array_map(function ($producto) {
            $producto->es_fiscalizado = (bool) $producto->es_fiscalizado;
            $producto->es_perecible = (bool) $producto->es_perecible;

            return $producto;
        }, $productos);

        return ApiResponse::success($productos);
    }

    /**
     * Crear un nuevo producto en el catálogo.
     */
    public function crear_producto(int $id_categoria, string $nombre, bool $es_fiscalizado, bool $es_perecible)
    {
        // 1. Validar nombre único
        if (Producto::where('nombre', $nombre)->where('estado', '!=', \App\Shared\Enums\EstadoBase::Inactivo->value)->exists()) {
            return ApiResponse::error('Ya existe un producto con este nombre.');
        }

        $producto_nuevo = Producto::create([
            'id_categoria' => $id_categoria,
            'nombre' => $nombre,
            'es_fiscalizado' => $es_fiscalizado ? 1 : 0,
            'es_perecible' => $es_perecible ? 1 : 0,
            'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
        ]);

        $producto = Producto::get_producto_by_id($producto_nuevo->id);

        if ($producto) {
            $producto->es_fiscalizado = (bool) $producto->es_fiscalizado;
            $producto->es_perecible = (bool) $producto->es_perecible;
        }

        return ApiResponse::success($producto, 'Producto registrado correctamente');
    }
}
