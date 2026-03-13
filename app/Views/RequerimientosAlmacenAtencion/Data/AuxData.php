<?php

namespace App\Views\RequerimientosAlmacenAtencion\Data;

use App\Models\KardexProducto;
use App\Models\LoteProducto;
use App\Shared\Enums\Kardex\OrigenMovimiento;
use App\Shared\Enums\Kardex\TipoMovimiento;
use Illuminate\Support\Facades\DB;

class AuxData
{

    /**
     * obtener la lista de almacenes donde el empleado es responsable
     */
    public static function get_almacenes(int $id_empleado): array
    {
        $sql = '
        SELECT DISTINCT
            alm.id AS id_almacen,
            alm.nombre
        FROM
            almacen alm
        INNER JOIN responsable_almacen res ON
            res.id_almacen = alm.id
        WHERE
            alm.estado = "Activo" AND
            alm.es_principal != 1 AND 
            res.estado = "Activo" AND 
            res.id_empleado = :id_empleado
        ';

        return DB::select($sql, ['id_empleado' => $id_empleado]);
    }

    // obtener la lista de empleados para indicar quien recibe
    public static function get_empleados()
    {
        return DB::select('
        SELECT DISTINCT
            emp.id AS id_empleado,
            CONCAT(emp.nombre, " ", emp.apellido) AS nombre_completo,
            emp.dni,
            emp.path_foto
        FROM
            empleado emp
        WHERE
            emp.estado = "Activo"
        ');
    }

    /**
     * Obtiene los lotes disponibles para un producto en un almacén
     */
    public static function get_lotes_disponibles(array $ids_productos, int $id_almacen)
    {
        $placeholders = implode(',', array_fill(0, count($ids_productos), '?'));

        return DB::select("
            SELECT 
                lp.id AS id_lote,
                lp.id_producto,
                lp.correlativo,
                lp.stock_actual,
                lp.stock_actual_base,
                lp.contenido_por_presentacion,
                uni.nombre AS unidad_medida,
                uni.abreviatura AS unidad_medida_abv,
                lp.fecha_hora_ingreso,
                lp.fecha_vencimiento,
                DATEDIFF(lp.fecha_vencimiento, NOW()) AS dias_para_vencer
            FROM 
                lote_producto lp
            INNER JOIN unidad_medida uni ON uni.id = lp.id_unidad_medida
            WHERE 
                lp.id_producto IN ($placeholders) AND 
                lp.id_almacen = ? AND 
                lp.stock_actual_base > 0 AND
                lp.estado = 'Activo'
            ORDER BY 
                lp.fecha_vencimiento ASC, 
                lp.created_at ASC
        ", [...$ids_productos, $id_almacen]);
    }

    /**
     * Registrar la entrega en el kardex
     */
    public static function registrar_kardex(
        int $id_lote,
        int $id_detalle_entrega,
        float $stock_anterior,
        float $stock_anterior_base,
        float $cantidad_lote,
        float $cantidad_base,
        float $nuevo_stock,
        float $nuevo_stock_base,
        string $descripcion
    ) {
        return KardexProducto::insertGetId([
            'id_lote_producto' => $id_lote,
            'id_origen' => $id_detalle_entrega,
            'tipo_origen' => OrigenMovimiento::Entrega->value,
            'tipo_movimiento' => TipoMovimiento::Salida->value,
            'descripcion' => $descripcion,
            'stock_anterior' => $stock_anterior,
            'stock_anterior_base' => $stock_anterior_base,
            'cantidad_movimiento' => $cantidad_lote,
            'cantidad_movimiento_base' => $cantidad_base,
            'stock_resultante' => $nuevo_stock,
            'stock_resultante_base' => $nuevo_stock_base,
            'created_at' => now(),
        ]);
    }

    /**
     * Actualizar stock del lote
     */
    public static function update_lote_stock(int $id_lote, float $stock_nuevo, float $stock_nuevo_base)
    {
        return LoteProducto::where('id', $id_lote)
            ->update([
                'stock_actual' => $stock_nuevo,
                'stock_actual_base' => $stock_nuevo_base
            ]);
    }

    /**
     * Obtener un lote por su id
     */
    public static function get_lote_by_id(int $id_lote)
    {
        return LoteProducto::select('correlativo', 'stock_actual', 'stock_actual_base')
            ->where('id', $id_lote)
            ->first();
    }
}
