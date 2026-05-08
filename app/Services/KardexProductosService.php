<?php

namespace App\Services;

use App\Data\KardexProductosData;
use App\Data\LotesProductosData;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Responses\ApiResponse;

class KardexProductosService
{
    /**
     * Metodo generico para realizar un registro en el kardex
     */
    public static function registrar_kardex(
        int $id_lote,
        //
        KardexTipoMovimiento $tipo_movimiento,
        KardexOrigenMovimiento $tipo_origen,
        string $descripcion,
        //
        float $cantidad_movimiento,
        float $cantidad_movimiento_base,
        float $nuevo_stock,
        float $nuevo_stock_base,
        //
        ?int $id_origen = null,
        ?float $stock_anterior = null,
        ?float $stock_anterior_base = null,
        ?string $created_at = null
    ) {
        // Consultar el costo promedio del producto del lote
        $costo_promedio = LotesProductosData::get_costo_promedio_producto($id_lote);

        return ApiResponse::success(KardexProductosData::registrar_kardex(
            $id_lote,
            $tipo_movimiento,
            $tipo_origen,
            $descripcion,
            $cantidad_movimiento,
            $cantidad_movimiento_base,
            $nuevo_stock,
            $nuevo_stock_base,
            $id_origen,
            $stock_anterior,
            $stock_anterior_base,
            $costo_promedio,
            $created_at
        ));
    }
}
