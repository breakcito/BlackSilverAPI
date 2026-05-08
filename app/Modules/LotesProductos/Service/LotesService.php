<?php

namespace App\Modules\LotesProductos\Service;

use App\Data\LotesProductosData;
use App\Services\KardexProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Responses\ApiResponse;
use App\Modules\LotesProductos\Data\AuxData;
use App\Modules\LotesProductos\Data\LotesData;
use Illuminate\Support\Facades\DB;

class LotesService
{
    /**
     * Listar lotes de un almacén.
     */
    public static function get_resumen_lotes(int $id_almacen)
    {
        $lotes = LotesData::get_resumen_lotes($id_almacen);

        return ApiResponse::success($lotes);
    }

    /**
     * Crear nuevo lote e insertar en Kardex si aplica.
     */
    public static function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        ?string $descripcion,
        float $stock_inicial,
        float $contenido_por_presentacion,
        string $fecha_hora_ingreso,
        ?string $fecha_vencimiento
    ) {
        $correlativoData = LotesProductosData::get_nuevo_correlativo($id_almacen);

        $stock_actual_base = $stock_inicial * $contenido_por_presentacion;

        $id_lote = LotesProductosData::crear_lote(
            id_producto: $id_producto,
            id_unidad_medida: $id_unidad_medida,
            id_almacen: $id_almacen,
            id_origen: null,
            tabla_origen: null,
            correlativo: $correlativoData["correlativo"],
            numero_correlativo: $correlativoData["numero_correlativo"],
            stock_inicial: $stock_inicial,
            contenido_por_presentacion: $contenido_por_presentacion,
            stock_actual_base: $stock_actual_base,
            fecha_hora_ingreso: $fecha_hora_ingreso,
            descripcion: $descripcion,
            fecha_vencimiento: $fecha_vencimiento
        );

        if ($stock_inicial > 0) {
            KardexProductosService::registrar_kardex(
                id_lote: $id_lote,
                tipo_movimiento: KardexTipoMovimiento::Ingreso,
                tipo_origen: KardexOrigenMovimiento::NuevoLote,
                descripcion: 'Ingreso por nuevo lote al almacén',
                cantidad_movimiento: $stock_inicial,
                cantidad_movimiento_base: $stock_actual_base,
                nuevo_stock: $stock_inicial,
                nuevo_stock_base: $stock_actual_base
            );
        }

        return ApiResponse::success(LotesData::get_lote_by_id(id_lote: $id_lote), 'Lote registrado correctamente');
    }

    /**
     * Ajustar stock de un lote (Corrección manual).
     */
    public static function ajustar_stock(int $id_lote, float $nuevo_stock, float $nuevo_stock_base, ?string $motivo = null)
    {
        return DB::transaction(function () use ($id_lote, $nuevo_stock, $nuevo_stock_base, $motivo) {
            $lote = LotesProductosData::get_lote_simple_by_id($id_lote);

            if (!$lote) {
                return ApiResponse::error('Lote no encontrado');
            }

            if ($lote['stock_actual_base'] == $nuevo_stock_base) {
                return ApiResponse::error('El nuevo stock es igual al actual');
            }

            $stock_anterior = $lote['stock_actual'];
            $stock_anterior_base = $lote['stock_actual_base'];
            $diferencia_base = $nuevo_stock_base - $stock_anterior_base;
            $diferencia_lote = $nuevo_stock - $stock_anterior;
            $tipo_movimiento = $diferencia_base > 0 ? KardexTipoMovimiento::Ingreso : KardexTipoMovimiento::Salida;

            $unidad_base = AuxData::get_abreviatura_unidad_medida($lote['id_unidad_medida']);

            $descripcion_kardex = $motivo;
            if (empty($descripcion_kardex)) {
                $abs_diff = abs($diferencia_base);
                if ($diferencia_base > 0) {
                    $descripcion_kardex = "Se hizo un aumento de {$abs_diff} {$unidad_base}";
                } else {
                    $descripcion_kardex = "Se retiraron {$abs_diff} {$unidad_base}";
                }
            }

            // Actualizar lote
            LotesProductosData::update_stock($id_lote, $nuevo_stock, $nuevo_stock_base);

            // Registrar movimiento en Kardex
            KardexProductosService::registrar_kardex(
                id_lote: $id_lote,
                tipo_movimiento: $tipo_movimiento,
                tipo_origen: KardexOrigenMovimiento::AjusteStock,
                descripcion: $descripcion_kardex,
                stock_anterior: $stock_anterior,
                stock_anterior_base: $stock_anterior_base,
                cantidad_movimiento: abs($diferencia_lote),
                cantidad_movimiento_base: abs($diferencia_base),
                nuevo_stock: $nuevo_stock,
                nuevo_stock_base: $nuevo_stock_base
            );

            return ApiResponse::success(LotesData::get_lote_by_id(id_lote: $id_lote), 'Stock del lote ajustado correctamente');
        });
    }
    /**
     * Obtener información de lotes para impresión de tickets.
     */
    public static function get_info_to_tickets(array $ids_lotes)
    {
        $info = LotesProductosData::get_info_to_ticket(ids_lotes: $ids_lotes);
        return ApiResponse::success($info);
    }
}
