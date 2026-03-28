<?php

namespace App\Views\PrestamosAlmacenAtencion\Data;

use Illuminate\Support\Facades\DB;

class AuxData
{
    /**
     * Obtiene los almacenes donde el empleado es responsable (no principales).
     */
    public static function get_almacenes_autorizados(int $id_empleado): array
    {
        return DB::select('
            SELECT DISTINCT
                alm.id AS id_almacen,
                alm.nombre
            FROM
                almacen alm
            INNER JOIN responsable_almacen res ON res.id_almacen = alm.id
            WHERE
                alm.estado = "Activo"
                AND alm.es_principal != 1
                AND res.estado = "Activo"
                AND res.id_empleado = :id_empleado
        ', ['id_empleado' => $id_empleado]);
    }

    /**
     * Obtiene los empleados activos para seleccionar como entregador o receptor.
     */
    public static function get_empleados(): array
    {
        return DB::select('
            SELECT
                emp.id AS id_empleado,
                CONCAT(emp.nombre, " ", emp.apellido) AS nombre_completo,
                emp.dni,
                emp.path_foto
            FROM empleado emp
            WHERE emp.estado = "Activo"
            ORDER BY emp.nombre ASC
        ');
    }

    /**
     * Obtiene los lotes disponibles de un producto en un almacén (para el despacho).
     */
    public static function get_lotes_disponibles(int $id_producto, int $id_almacen): array
    {
        return DB::select('
            SELECT
                lp.id AS id_lote,
                lp.id_producto,
                lp.correlativo,
                lp.stock_actual,
                lp.stock_actual_base,
                lp.contenido_por_presentacion,
                um.nombre AS unidad_medida,
                um.abreviatura AS unidad_medida_abv,
                lp.fecha_vencimiento,
                DATEDIFF(lp.fecha_vencimiento, NOW()) AS dias_para_vencer
            FROM lote_producto lp
            INNER JOIN unidad_medida um ON um.id = lp.id_unidad_medida
            WHERE
                lp.id_producto = :id_producto
                AND lp.id_almacen = :id_almacen
                AND lp.stock_actual_base > 0
                AND lp.estado = "Activo"
            ORDER BY lp.fecha_vencimiento ASC, lp.created_at ASC
        ', ['id_producto' => $id_producto, 'id_almacen' => $id_almacen]);
    }

    /**
     * Obtiene un lote por su ID para validar stock.
     */
    public static function get_lote_by_id(int $id_lote)
    {
        return \App\Models\LoteProducto::select('id', 'correlativo', 'id_producto', 'contenido_por_presentacion', 'stock_actual', 'stock_actual_base')
            ->where('id', $id_lote)
            ->first();
    }

    /**
     * Actualiza el stock de un lote.
     */
    public static function update_lote_stock(int $id_lote, float $nuevo_stock, float $nuevo_stock_base): void
    {
        \App\Models\LoteProducto::where('id', $id_lote)->update([
            'stock_actual'      => $nuevo_stock,
            'stock_actual_base' => $nuevo_stock_base,
        ]);
    }

    /**
     * Registra un movimiento de Kardex (salida desde el almacén prestamista).
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
    ): void {
        \App\Models\KardexProducto::insert([
            'id_lote_producto'          => $id_lote,
            'id_origen'                 => $id_detalle_entrega,
            'tipo_origen'               => \App\Shared\Enums\Kardex\OrigenMovimiento::Entrega->value,
            'tipo_movimiento'           => \App\Shared\Enums\Kardex\TipoMovimiento::Salida->value,
            'descripcion'               => $descripcion,
            'stock_anterior'            => $stock_anterior,
            'stock_anterior_base'       => $stock_anterior_base,
            'cantidad_movimiento'       => $cantidad_lote,
            'cantidad_movimiento_base'  => $cantidad_base,
            'stock_resultante'          => $nuevo_stock,
            'stock_resultante_base'     => $nuevo_stock_base,
            'created_at'                => now(),
        ]);
    }

    /**
     * Obtiene los lotes disponibles de varios productos en un almacén.
     */
    public static function get_lotes_disponibles_batch(array $ids_productos, int $id_almacen): array
    {
        return DB::select("
            SELECT
                lp.id AS id_lote,
                lp.id_producto,
                lp.correlativo,
                lp.stock_actual,
                lp.stock_actual_base,
                lp.contenido_por_presentacion,
                um.nombre AS unidad_medida,
                um.abreviatura AS unidad_medida_abv,
                lp.fecha_vencimiento,
                DATEDIFF(lp.fecha_vencimiento, NOW()) AS dias_para_vencer
            FROM lote_producto lp
            INNER JOIN unidad_medida um ON um.id = lp.id_unidad_medida
            WHERE
                lp.id_producto IN (" . implode(',', array_fill(0, count($ids_productos), '?')) . ")
                AND lp.id_almacen = ?
                AND lp.stock_actual_base > 0
                AND lp.estado = 'Activo'
            ORDER BY lp.fecha_vencimiento ASC, lp.created_at ASC
        ", [...$ids_productos, $id_almacen]);
    }

    /**
     * Obtiene unidades de medida 
     */
    public static function get_unidades_medida(): array
    {
        return DB::select('
            SELECT id AS id_unidad_medida, nombre, abreviatura
            FROM unidad_medida
            ORDER BY nombre ASC
        ');
    }

    /**
     * Obtener los lotes disponibles del almacén que recibe (destino) para reposición.
     */
    public static function get_lotes_disponibles_destino(int $id_almacen_solicitante, array $id_productos): array
    {
        if (empty($id_productos)) {
            return [];
        }

        $inQuery = implode(',', array_fill(0, count($id_productos), '?'));

        $sql = "
            SELECT DISTINCT
                lp.id AS id_lote,
                lp.id_producto,
                lp.id_unidad_medida as id_unidad_medida_lote,
                p.id_unidad_medida_base,
                um_base.abreviatura as unidad_medida_base_abv,
                um_lote.abreviatura AS unidad_medida_lote_abv,
                lp.descripcion,
                lp.correlativo,
                lp.stock_actual,
                lp.stock_actual_base,
                lp.contenido_por_presentacion,
                lp.fecha_hora_ingreso,
                lp.fecha_vencimiento,
                lp.estado,
                p.stock_minimo,
                p.dias_espera_vencimiento,
                lp.created_at,
                /* Cálculo de días restantes */
                CASE 
                    WHEN lp.fecha_vencimiento IS NOT NULL THEN 
                        DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) 
                    ELSE NULL
                END AS dias_para_vencer,
                /* Determinación del estado de vencimiento */
                CASE
                    WHEN p.es_perecible != 1 THEN 'N/A'
                    WHEN lp.fecha_vencimiento IS NULL THEN 'Sin fecha'
                    WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) < 0 THEN 'Vencido'
                    WHEN DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) <= p.dias_espera_vencimiento THEN 'Por vencer'
                    ELSE 'Vigente'
                END AS estado_vencimiento
            FROM
                lote_producto lp
            INNER JOIN producto p ON
                p.id = lp.id_producto
            LEFT JOIN unidad_medida um_base ON
                um_base.id = p.id_unidad_medida_base
            LEFT JOIN unidad_medida um_lote ON
                um_lote.id = lp.id_unidad_medida
            WHERE
                lp.id_almacen = ? AND
                lp.estado = 'Activo' AND
                lp.stock_actual_base > 0 AND
                lp.id_producto IN ($inQuery)
            HAVING estado_vencimiento != 'Vencido'
            ORDER BY lp.fecha_hora_ingreso, lp.created_at
        ";

        $params = array_merge([$id_almacen_solicitante], $id_productos);
        return DB::select($sql, $params);
    }
}
