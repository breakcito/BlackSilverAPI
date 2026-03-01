<?php

namespace App\Services;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Models\UnidadMedida;
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
        $unidades = UnidadMedida::select('id as id_unidad_medida', 'nombre', 'abreviatura')
            ->orderBy('nombre', 'asc')
            ->get();

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
            $prefijo = 'LOT';
            $correlativoData = \App\Shared\Helpers\CorrelativoHelper::generar('lote_producto', $prefijo, [], 5, \App\Shared\Enums\Periodo::Anual);

            $lote = LoteProducto::create([
                'id_producto' => $id_producto,
                'id_unidad_medida' => $id_unidad_medida,
                'id_almacen' => $id_almacen,
                'descripcion' => $descripcion,
                'correlativo' => $prefijo,
                'numero_correlativo' => $correlativoData['numero_correlativo'],
                'stock_actual' => $stock_inicial,
                'fecha_ingreso' => $fecha_ingreso,
                'fecha_vencimiento' => $fecha_vencimiento,
                'created_at' => now(),
                'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
            ]);
            $id_lote = $lote->id;

            if ($stock_inicial > 0) {
                KardexProducto::create([
                    'id_lote_producto' => $id_lote,
                    'id_cabecera' => null,
                    'codigo_movimiento' => CodigoMovimiento::NuevoLote->value,
                    'tipo_movimiento' => TipoMovimiento::Ingreso->value,
                    'cantidad_anterior' => 0,
                    'cantidad_movimiento' => $stock_inicial,
                    'cantidad_resultante' => $stock_inicial,
                    'glosa' => 'Stock Inicial por Creación de Lote',
                    'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
                ]);
            }

            return ApiResponse::success(LoteProducto::get_lote_by_id($id_lote), 'Lote registrado correctamente');
        });
    }
}
