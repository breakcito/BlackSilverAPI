<?php

namespace App\Views\PrestamosAlmacen\Data;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use App\Shared\Enums\EstadoBase;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class RecepcionData
{
    /**
     * Registra la recepción en un nuevo lote.
     */
    public static function registrar_recepcion_lote_nuevo(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        ?string $fecha_vencimiento,
        float $cantidad_en_unidad,
        float $cantidad_base,
        float $contenido_por_presentacion,
        ?string $descripcion = null,
        ?string $fecha_hora_ingreso = null
    ): int {
        $correlativoData = CorrelativoHelper::generar(
            tabla: 'lote_producto',
            prefijo: 'LOT',
            filtros: ['id_almacen' => $id_almacen],
            columnaFecha: 'fecha_hora_ingreso'
        );

        return LoteProducto::insertGetId([
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'id_almacen' => $id_almacen,
            'descripcion' => $descripcion ?: 'Lote generado por reposición de préstamo',
            'correlativo' => $correlativoData['correlativo'],
            'numero_correlativo' => $correlativoData['numero_correlativo'],
            'stock_actual' => $cantidad_en_unidad,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'stock_actual_base' => $cantidad_base,
            'fecha_hora_ingreso' => $fecha_hora_ingreso ?: now(),
            'fecha_vencimiento' => $fecha_vencimiento,
            'estado' => EstadoBase::Activo->value,
            'created_at' => now(),
        ]);
    }

    /**
     * Registra la recepción en un lote existente, incrementando su stock.
     */
    public static function registrar_recepcion_lote_existente(int $id_lote, float $cantidad_base): array
    {
        $lote = LoteProducto::where('id', $id_lote)->first();
        if (!$lote) {
            throw new \Exception("No se encontró el lote con ID {$id_lote}");
        }

        $incremento_lote = ($lote->contenido_por_presentacion > 0) ? ($cantidad_base / $lote->contenido_por_presentacion) : 0;

        $lote->update([
            'stock_actual' => DB::raw("stock_actual + $incremento_lote"),
            'stock_actual_base' => DB::raw("stock_actual_base + $cantidad_base"),
        ]);

        return ['lote' => $lote, 'cantidad_lote_ingresada' => $incremento_lote];
    }


    /**
     * Registra el ingreso al kardex para una recepción.
     */
    public static function registrar_kardex_recepcion(
        int $id_lote_producto,
        int $id_origen,
        float $cantidad_movimiento,
        float $cantidad_movimiento_base,
        string $descripcion
    ): int {
        $lote = LoteProducto::where('id', $id_lote_producto)->first();
        if (!$lote) {
            throw new \Exception("No se encontró el lote con ID {$id_lote_producto}");
        }

        // El stock después de ajustar (asumiendo que ya se actualizó en el lote)
        $stock_resultante = $lote->stock_actual;
        $stock_resultante_base = $lote->stock_actual_base;
        $stock_anterior = $stock_resultante - $cantidad_movimiento;
        $stock_anterior_base = $stock_resultante_base - $cantidad_movimiento_base;

        return KardexProducto::insertGetId([
            'id_lote_producto' => $id_lote_producto,
            'id_origen' => $id_origen,
            'tipo_origen' => OrigenMovimiento::Recepcion->value,
            'tipo_movimiento' => TipoMovimiento::Ingreso->value,
            'stock_anterior' => $stock_anterior,
            'stock_anterior_base' => $stock_anterior_base,
            'cantidad_movimiento' => $cantidad_movimiento,
            'cantidad_movimiento_base' => $cantidad_movimiento_base,
            'stock_resultante' => $stock_resultante,
            'stock_resultante_base' => $stock_resultante_base,
            'descripcion' => $descripcion,
            'created_at' => now(),
        ]);
    }
}
