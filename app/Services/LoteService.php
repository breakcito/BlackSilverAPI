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
            $nuevo_numero = \App\Shared\Helpers\CorrelativoHelper::proximoNumero('lote_producto', 'numero_correlativo', true);
            $correlativo = 'LOT';

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

            if ($stock_inicial > 0) {
                KardexProducto::crear_movimiento(
                    $id_lote,
                    null,
                    CodigoMovimiento::NuevoLote->value,
                    TipoMovimiento::Ingreso->value,
                    0,
                    $stock_inicial,
                    $stock_inicial,
                    'Stock Inicial por Creación de Lote'
                );
            }

            return ApiResponse::success(LoteProducto::get_lote_by_id($id_lote), 'Lote registrado correctamente');
        });
    }
}
