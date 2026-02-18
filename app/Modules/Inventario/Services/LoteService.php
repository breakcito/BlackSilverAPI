<?php

namespace App\Modules\Inventario\Services;

use App\Modules\Inventario\Models\KardexProducto;
use App\Modules\Inventario\Models\LoteProducto;
use App\Modules\Inventario\Models\UnidadMedida;
use App\Shared\Enums\CodigoMovimiento;
use App\Shared\Enums\TipoMovimiento;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class LoteService
{
    /**
     * Listar lotes de un almacén.
     */
    public function get_lotes_by_almacen(int $id_almacen)
    {
        $lotes = LoteProducto::get_lotes_by_almacen($id_almacen);
        return ApiResponse::success($lotes);
    }

    /**
     * Listar productos disponibles para sugerir (filtramos servicios).
     */
    public function get_productos_para_lote()
    {
        $productos = LoteProducto::get_productos_para_lote();
        // Formatear perecible para front si es necesario, aunque ya viene como 1/0
        return ApiResponse::success($productos);
    }

    /**
     * Listar unidades de medida.
     */
    public function get_unidades_medida()
    {
        $unidades = UnidadMedida::get_unidades_medida();
        return ApiResponse::success($unidades);
    }

    /**
     * Crear nuevo lote e insertar en Kardex si aplica.
     */
    public function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        ?string $descripcion,
        float $stock_inicial,
        string $fecha_ingreso,
        ?string $fecha_vencimiento
    ) {
        return DB::transaction(function () use (
            $id_producto,
            $id_unidad_medida,
            $id_almacen,
            $descripcion,
            $stock_inicial,
            $fecha_ingreso,
            $fecha_vencimiento
        ) {
            // 1. Generar Correlativo: LOT-001 (Global o por almacén, usaremos global simplificado por ahora)
            // OJO: Si stock > 0, es un ingreso físico. Si es 0, es solo alta de lote.
            
            $ultimo_numero = LoteProducto::get_ultimo_correlativo();
            $nuevo_numero = $ultimo_numero + 1;
            $correlativo = 'LOT';

            // 2. Crear Lote
            $id_lote = LoteProducto::crear_lote(
                $id_producto,
                $id_unidad_medida,
                $id_almacen,
                $descripcion,
                $correlativo,
                $nuevo_numero,
                $stock_inicial,
                $fecha_ingreso,
                $fecha_vencimiento
            );

            // 3. Registrar en Kardex si hay stock inicial
            if ($stock_inicial > 0) {
                // Nuevo Lote => Tipo INGRESO
                // Codigo movimiento: Nuevo Lote
                // Cabecera: NULL (porque es carga inicial o directo)
                
                KardexProducto::crear_movimiento(
                    $id_lote,
                    null, // Sin cabecera padre por ahora
                    CodigoMovimiento::NuevoLote->value, // codigo_movimiento (Enum)
                    TipoMovimiento::Ingreso->value,    // tipo_movimiento (Enum)
                    0,            // cantidad_anterior
                    $stock_inicial, // cantidad_movimiento
                    $stock_inicial, // cantidad_resultante
                    'Stock Inicial por Creación de Lote' // glosa
                );
            }

            return ApiResponse::success(['id_lote' => $id_lote], 'Lote registrado correctamente');
        });
    }
}
