<?php

namespace App\Services;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Shared\Enums\CodigoMovimiento;
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
        string $fecha_ingreso,
        ?string $fecha_vencimiento
    ) {
        $prefijo = 'LOT';
        $correlativoData = CorrelativoHelper::generar('lote_producto', $prefijo, [], 5, Periodo::Anual);

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
            'estado' => EstadoBase::Activo->value,
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
                'estado' => EstadoBase::Activo->value,
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
            CONCAT(lp.correlativo, '-', DATE_FORMAT(lp.created_at, '%y'), '-', LPAD(lp.numero_correlativo, 5, '0')) AS codigo_lote,
            lp.descripcion,
            lp.stock_actual,
            um.abreviatura AS unidad_medida,
            lp.fecha_ingreso,
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
            lp.fecha_ingreso ASC
        ";

        $data = DB::select($sql, [
            'id_producto' => $id_producto,
            'id_almacen' => $id_almacen,
        ]);

        return ApiResponse::success($data);
    }
}
