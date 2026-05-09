<?php
namespace App\Services;

use App\Data\LotesProductosData;
use App\Data\UnidadesMedidaData;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class LotesProductosService
{
    /**
     * Obtener los lotes disponibles de un almacen, util para cualquier 
     * tipo de entregas o ingresos. Solo se traen lotes activos, con stock y
     * no vencidos.
     */
    public static function get_lotes_disponibles(
        int $id_almacen,
        array $ids_productos
    ) {
        $lotes = LotesProductosData::get_lotes_disponibles(
            id_almacen: $id_almacen,
            ids_productos: $ids_productos
        );

        return ApiResponse::success($lotes);
    }

    /**
     * Crear nuevo lote e insertar en Kardex si aplica.
     */
    public static function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        int|null $id_origen,
        //
        string|null $tabla_origen,
        //
        float $contenido_por_presentacion,
        float $stock_inicial,
        //
        string $fecha_hora_ingreso,
        ?string $descripcion = null,
        ?string $fecha_vencimiento = null,
    ) {
        $correlativoData = LotesProductosData::get_nuevo_correlativo($id_almacen);
        $costo_promedio_base = LotesProductosData::get_costo_promedio_producto($id_producto);

        $id_lote = LotesProductosData::crear_lote(
            id_producto: $id_producto,
            id_unidad_medida: $id_unidad_medida,
            id_almacen: $id_almacen,
            id_origen: $id_origen,
            //
            tabla_origen: $tabla_origen,
            //
            correlativo: $correlativoData["correlativo"],
            numero_correlativo: $correlativoData["numero_correlativo"],
            //
            contenido_por_presentacion: $contenido_por_presentacion,
            stock_inicial: $stock_inicial,
            //
            costo_promedio_base: $costo_promedio_base,
            //
            fecha_hora_ingreso: $fecha_hora_ingreso,
            descripcion: $descripcion,
            fecha_vencimiento: $fecha_vencimiento
        );

        if ($stock_inicial > 0) {
            KardexProductosService::registrar_kardex(
                id_lote: $id_lote,
                //
                tipo_movimiento: KardexTipoMovimiento::Ingreso,
                tipo_origen: KardexOrigenMovimiento::NuevoLote,
                descripcion: 'Ingreso por nuevo lote al almacén',
                //
                cantidad_movimiento: $stock_inicial,
                cantidad_movimiento_base: $stock_inicial * $contenido_por_presentacion,
                //
                nuevo_stock: $stock_inicial,
                nuevo_stock_base: $stock_inicial * $contenido_por_presentacion,
                //
                id_origen: $id_origen,
                tabla_origen: $tabla_origen,
                //
                stock_anterior: 0,
                stock_anterior_base: 0,
                //
                costo_promedio_base: $costo_promedio_base,
                //
                created_at: $fecha_hora_ingreso
            );
        }

        return ApiResponse::success($id_lote);
    }


    /**
     * Actualizar stock de lote e insertar en Kardex si aplica.
     */
    public static function update_stock(
        int $id_lote,
        int|null $id_origen,
        //
        string|null $tabla_origen,
        KardexOrigenMovimiento $tipo_origen,
        //
        float $nuevo_stock_base,
        //
        ?string $descripcion = null,
        ?string $created_at = null
    ) {
        return DB::transaction(function () use ($id_lote, $id_origen, $tabla_origen, $tipo_origen, $nuevo_stock_base, $descripcion, $created_at) {
            $lote = LotesProductosData::get_lote_simple_by_id($id_lote);

            if (!$lote) {
                return ApiResponse::error('Lote no encontrado');
            }

            if ($lote['stock_actual_base'] == $nuevo_stock_base) {
                return ApiResponse::error('El nuevo stock es igual al actual');
            }

            // lo que habia antes
            $stock_anterior = $lote['stock_actual'];
            $stock_anterior_base = $lote['stock_actual_base'];

            // el nuevo stock
            $nuevo_stock = $nuevo_stock_base * $lote['contenido_por_presentacion'];

            // las diferencias
            $diferencia_lote = $nuevo_stock - $stock_anterior;
            $diferencia_base = $nuevo_stock_base - $stock_anterior_base;

            // que tipo de movimiento es
            $tipo_movimiento = $diferencia_base > 0 ? KardexTipoMovimiento::Ingreso : KardexTipoMovimiento::Salida;

            $unidad_lote = UnidadesMedidaData::get_unidades(
                id_unidad_medida: $lote['id_unidad_medida']
            )->abreviatura;

            if ($descripcion == null || empty($descripcion)) {
                if ($diferencia_lote < 0) {
                    $descripcion = "Se hizo un aumento de {$diferencia_lote} {$unidad_lote}";
                } else {
                    $descripcion = "Se retiraron {$diferencia_lote} {$unidad_lote}";
                }
            }

            // Actualizar stock del lote lote
            LotesProductosData::update_stock($id_lote, $nuevo_stock, $nuevo_stock_base);

            // Registrar movimiento en Kardex
            $costo_promedio_base = LotesProductosData::get_costo_promedio_producto($lote['id_producto']);
            KardexProductosService::registrar_kardex(
                id_lote: $id_lote,
                //
                tipo_movimiento: $tipo_movimiento,
                tipo_origen: $tipo_origen,
                descripcion: $descripcion,
                //
                cantidad_movimiento: abs($diferencia_lote),
                cantidad_movimiento_base: abs($diferencia_base),
                //
                nuevo_stock: $nuevo_stock,
                nuevo_stock_base: $nuevo_stock_base,
                //
                id_origen: $id_origen,
                tabla_origen: $tabla_origen,
                //
                stock_anterior: $stock_anterior,
                stock_anterior_base: $stock_anterior_base,
                //
                costo_promedio_base: $costo_promedio_base,
                //
                created_at: $created_at
            );

            return ApiResponse::success(true, 'Stock del lote actualizado correctamente');
        });
    }
}