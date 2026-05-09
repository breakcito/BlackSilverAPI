<?php

namespace App\Modules\LotesProductos\Service;

use App\Data\LotesProductosData;
use App\Services\LotesProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Responses\ApiResponse;
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
        $new_lote_response = LotesProductosService::crear_lote(
            id_producto: $id_producto,
            id_unidad_medida: $id_unidad_medida,
            id_almacen: $id_almacen,
            id_origen: null,
            //
            tabla_origen: null,
            //
            contenido_por_presentacion: $contenido_por_presentacion,
            stock_inicial: $stock_inicial,
            //
            fecha_hora_ingreso: $fecha_hora_ingreso,
            descripcion: $descripcion,
            fecha_vencimiento: $fecha_vencimiento
        );

        $id_lote = $new_lote_response['data'];
        return ApiResponse::success(LotesData::get_lote_by_id(id_lote: $id_lote), 'Lote registrado correctamente');
    }

    /**
     * Ajustar stock de un lote (Corrección manual).
     */
    public static function ajustar_stock(int $id_lote, float $nuevo_stock_base, ?string $motivo = null)
    {
        return DB::transaction(function () use ($id_lote, $nuevo_stock_base, $motivo) {
            LotesProductosService::update_stock(
                id_lote: $id_lote,
                id_origen: null,
                tabla_origen: null,
                tipo_origen: KardexOrigenMovimiento::AjusteStock,
                nuevo_stock_base: $nuevo_stock_base,
                descripcion: $motivo
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
