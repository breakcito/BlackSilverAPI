<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\KardexProducto;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;

class KardexData
{
    /**
     * Registrar en el kardex tras una reposicion por prestamos
     * realizada por logistica al almacen prestamista
     */
    public static function registrar_kardex(
        int $id_lote,
        int $id_origen,
        //
        string $descripcion,
        //
        float $stock_anterior,
        float $stock_anterior_base,
        float $cantidad_movimiento,
        float $cantidad_movimiento_base,
        float $nuevo_stock,
        float $nuevo_stock_base,
    ) {
        return KardexProducto::registrar_kardex(
            $id_lote,
            $id_origen,
            //
            TipoMovimiento::Salida,
            OrigenMovimiento::Entrega,
            $descripcion,
            //
            $stock_anterior,
            $stock_anterior_base,
            $cantidad_movimiento,
            $cantidad_movimiento_base,
            $nuevo_stock,
            $nuevo_stock_base,
        );
    }
}
