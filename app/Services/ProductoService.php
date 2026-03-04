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

        foreach ($productos as $producto) {
            $this->formatear_producto($producto);
        }

        return ApiResponse::success($productos);
    }

    /**
     * Crear un nuevo producto en el catálogo.
     */
    public function crear_producto(
        int $id_categoria,
        int $id_unidad_medida_base,
        string $nombre,
        bool $es_fiscalizado,
        bool $es_perecible,
        float $stock_minimo = 0,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null,
        ?int $dias_espera_vencimiento = null
    ) {
        // 1. Validar nombre único
        if (Producto::where('nombre', $nombre)->where('estado', '!=', \App\Shared\Enums\EstadoBase::Inactivo->value)->exists()) {
            return ApiResponse::error('Ya existe un producto con este nombre.');
        }

        // 2. Si no es perecible, limpiamos los campos de vencimiento
        if (! $es_perecible) {
            $tiempo_espera_vencimiento = null;
            $periodo_espera_vencimiento = null;
            $dias_espera_vencimiento = null;
        } else {
            // 3. Calcular días si están presentes tiempo y periodo
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

        $producto_nuevo = Producto::create([
            'id_categoria' => $id_categoria,
            'id_unidad_medida_base' => $id_unidad_medida_base,
            'nombre' => $nombre,
            'es_fiscalizado' => $es_fiscalizado,
            'es_perecible' => $es_perecible,
            'stock_minimo' => $stock_minimo,
            'tiempo_espera_vencimiento' => $tiempo_espera_vencimiento,
            'periodo_espera_vencimiento' => $periodo_espera_vencimiento,
            'dias_espera_vencimiento' => $dias_espera_vencimiento,
            'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
        ]);

        $producto = Producto::get_producto_by_id($producto_nuevo->id);

        if ($producto) {
            $this->formatear_producto($producto);
        }

        return ApiResponse::success($producto, 'Producto registrado correctamente');
    }

    /**
     * Formatear tipos de datos del producto para el frontend.
     */
    private function formatear_producto($producto): void
    {
        $producto->es_fiscalizado = (bool) $producto->es_fiscalizado;
        $producto->es_perecible = (bool) $producto->es_perecible;
        $producto->stock_minimo = (float) $producto->stock_minimo;
        if (isset($producto->tiempo_espera_vencimiento)) {
            $producto->tiempo_espera_vencimiento = (int) $producto->tiempo_espera_vencimiento;
        }
        if (isset($producto->dias_espera_vencimiento)) {
            $producto->dias_espera_vencimiento = (int) $producto->dias_espera_vencimiento;
        }
    }

    /**
     * Listar productos disponibles para sugerir (filtramos servicios).
     */
    public function get_productos_para_lote()
    {
        $productos = Producto::get_productos_para_lote();
        return ApiResponse::success($productos);
    }
}
