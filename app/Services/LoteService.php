<?php

namespace App\Services;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Models\Producto;
use App\Shared\Enums\OrigenMovimiento;
use App\Shared\Enums\EstadoBase;
use App\Shared\Enums\Periodo;
use App\Shared\Enums\TipoMovimiento;
use App\Shared\Helpers\CorrelativoHelper;
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
     * Crear nuevo lote e insertar en Kardex si aplica.
     */
    public function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        ?string $descripcion,
        float $stock_inicial,
        float $contenido_por_presentacion,
        string $fecha_hora_ingreso,
        ?string $fecha_vencimiento
    ) {
        $prefijo = 'LOT';
        // Usamos fecha_hora_ingreso para el reseteo del correlativo anual
        $correlativoData = CorrelativoHelper::generar(
            'lote_producto', 
            $prefijo, 
            [], 
            5, 
            Periodo::Anual, 
            'fecha_hora_ingreso'
        );

        $stock_actual_base = $stock_inicial * $contenido_por_presentacion;

        $lote = LoteProducto::create([
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'id_almacen' => $id_almacen,
            'descripcion' => $descripcion,
            'correlativo' => $correlativoData['correlativo'],
            'numero_correlativo' => $correlativoData['numero_correlativo'],
            'stock_actual' => $stock_inicial,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'stock_actual_base' => $stock_actual_base,
            'fecha_hora_ingreso' => $fecha_hora_ingreso,
            'fecha_vencimiento' => $fecha_vencimiento,
            'created_at' => now(),
            'estado' => EstadoBase::Activo->value,
        ]);

        $id_lote = $lote->id;

        if ($stock_inicial > 0) {
            KardexProducto::create([
                'id_lote_producto' => $id_lote,
                'id_origen' => null,
                'tipo_origen' => OrigenMovimiento::NuevoLote->value,
                'tipo_movimiento' => TipoMovimiento::Ingreso->value,
                'stock_anterior' => 0,
                'stock_anterior_base' => 0,
                'cantidad_movimiento' => $stock_inicial,
                'cantidad_movimiento_base' => $stock_actual_base,
                'stock_resultante' => $stock_inicial,
                'stock_resultante_base' => $stock_actual_base,
                'descripcion' => 'Ingreso por Nuevo Lote en almacén',
                'created_at' => now(),
            ]);
        }

        return ApiResponse::success(LoteProducto::get_lote_by_id($id_lote), 'Lote registrado correctamente');
    }


    /**
     * Obtiene los lotes disponibles para un producto en un almacén, con lógica FEFO/FIFO.
     */
    public function obtener_lotes_disponibles(int $id_producto, int $id_almacen)
    {
        $sql = "
        SELECT
            lp.id AS id_lote,
            lp.correlativo AS codigo_lote,
            lp.descripcion,
            lp.stock_actual,
            um.abreviatura AS unidad_medida,
            lp.fecha_hora_ingreso,
            lp.fecha_vencimiento,
            DATEDIFF(lp.fecha_vencimiento, CURDATE()) AS dias_para_vencer
        FROM
            lote_producto lp
        INNER JOIN unidad_medida um ON um.id = lp.id_unidad_medida
        WHERE
            lp.id_producto = :id_producto
            AND lp.id_almacen = :id_almacen
            AND lp.stock_actual > 0
            AND lp.estado = 'Activo'
        ORDER BY
            lp.fecha_vencimiento ASC,
            lp.fecha_hora_ingreso ASC
        ";

        $data = DB::select($sql, [
            'id_producto' => $id_producto,
            'id_almacen' => $id_almacen,
        ]);

        return ApiResponse::success($data);
    }

    /**
     * Listar productos disponibles para sugerir en la creación de lotes.
     */
    public function get_productos_para_lote()
    {
        $productos = Producto::get_productos_para_lote();
        return ApiResponse::success($productos);
    }

    /**
     * Ajustar stock de un lote (Corrección manual).
     */
    public function ajustar_stock(int $id_lote, float $nuevo_stock, float $nuevo_stock_base, ?string $motivo = null)
    {
        return DB::transaction(function () use ($id_lote, $nuevo_stock, $nuevo_stock_base, $motivo) {
            $lote = LoteProducto::find($id_lote);
            if (!$lote) {
                return ApiResponse::error('Lote no encontrado');
            }

            if ($lote->stock_actual_base == $nuevo_stock_base) {
                return ApiResponse::error('El nuevo stock es igual al actual');
            }

            $stock_anterior = $lote->stock_actual;
            $stock_anterior_base = $lote->stock_actual_base;
            $diferencia_base = $nuevo_stock_base - $stock_anterior_base;
            $diferencia_lote = $nuevo_stock - $stock_anterior;
            $tipo_movimiento = $diferencia_base > 0 ? TipoMovimiento::Ingreso : TipoMovimiento::Salida;
            
            // Obtener abreviatura de unidad base para la descripción automática
            $producto = DB::table('producto as p')
                ->join('unidad_medida as um', 'um.id', '=', 'p.id_unidad_medida_base')
                ->where('p.id', $lote->id_producto)
                ->select('um.abreviatura as unidad_base')
                ->first();
            
            $unidad_base = $producto ? $producto->unidad_base : '';

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
            $lote->update([
                'stock_actual' => $nuevo_stock,
                'stock_actual_base' => $nuevo_stock_base
            ]);

            // Registrar movimiento en Kardex
            KardexProducto::create([
                'id_lote_producto' => $id_lote,
                'id_origen' => null,
                'tipo_origen' => OrigenMovimiento::AjusteStock->value,
                'tipo_movimiento' => $tipo_movimiento->value,
                'stock_anterior' => $stock_anterior,
                'stock_anterior_base' => $stock_anterior_base,
                'cantidad_movimiento' => abs($diferencia_lote),
                'cantidad_movimiento_base' => abs($diferencia_base),
                'stock_resultante' => $nuevo_stock,
                'stock_resultante_base' => $nuevo_stock_base,
                'descripcion' => $descripcion_kardex,
                'created_at' => now(),
            ]);

            return ApiResponse::success(LoteProducto::get_lote_by_id($id_lote), 'Stock del lote ajustado correctamente');
        });
    }
}
