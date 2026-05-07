<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Data;

use App\Data\LotesProductosData;
use App\Models\RequerimientoAlmacenDetalle;
use App\Models\RequerimientoAlmacenDetalleLog;
use App\Models\SolicitudReabastecimiento;
use Illuminate\Support\Facades\DB;

class AuxData
{
    /**
     * Actualiza el estado de un detalle de requerimiento
     */
    public static function update_detalle_requerimiento_estado(int $id_detalle, string $estado, int $id_empleado, ?string $comentario = null)
    {
        $updateData = [
            'estado' => $estado,
            'id_empleado_atencion' => $id_empleado
        ];

        if ($comentario !== null) {
            $updateData['comentario_decision'] = $comentario;
        }

        return RequerimientoAlmacenDetalle::where('id', $id_detalle)
            ->update($updateData);
    }

    /**
     * Inserta un log de trazabilidad para un detalle de requerimiento
     */
    public static function insert_detalle_requerimiento_log(int $id_detalle, int $id_empleado, string $descripcion, string $estado)
    {
        return RequerimientoAlmacenDetalleLog::insertGetId([
            'id_requerimiento_almacen_detalle' => $id_detalle,
            'id_empleado' => $id_empleado,
            'descripcion' => $descripcion,
            'estado' => $estado,
            'created_at' => now()
        ]);
    }

    /**
     * Obtiene los almacenes que tienen stock de los productos solicitados
     */
    public static function get_almacenes_con_stock(int $id_almacen_excluido, array $ids_productos)
    {
        if (empty($ids_productos)) {
            return [];
        }

        // Sacamos la cantidad de productos únicos que estamos buscando
        $ids_unicos = array_unique($ids_productos);
        $totalIds = count($ids_unicos);

        $placeholders = implode(',', array_fill(0, $totalIds, '?'));

        $sql = "
        SELECT
            alm.id AS id_almacen,
            alm.nombre
        FROM
            almacen alm
        INNER JOIN 
            lote_producto lot ON lot.id_almacen = alm.id
        WHERE
            alm.es_principal = 0 AND 
            alm.estado = 'Activo' AND
            alm.id != ? AND
            lot.estado = 'Activo' AND -- Asegurar que el lote esté activo
            lot.id_producto IN ($placeholders) AND
            lot.stock_actual_base > 0 AND -- El lote específico debe tener stock
            (lot.fecha_vencimiento > NOW() OR lot.fecha_vencimiento IS NULL)
        GROUP BY
            alm.id,
            alm.nombre
        HAVING
            COUNT(DISTINCT lot.id_producto) = ?; -- MAGIA: Debe tener TODOS los productos buscados
        ";

        // Unimos los bindings: id_excluido, ids de productos, y el total al final para el HAVING
        $bindings = array_merge([$id_almacen_excluido], $ids_unicos, [$totalIds]);

        return DB::select($sql, $bindings);
    }

    /**
     * Obtiene el stock total de uno o varios productos en un almacén específico.
     * Solo suma el stock de lotes activos y que no estén vencidos.
     */
    public static function get_stock_total_almacen_por_productos(int $id_almacen, array $ids_productos)
    {
        // Validación de seguridad para evitar errores SQL si el array viene vacío
        if (empty($ids_productos)) {
            return [];
        }

        // 1. Creamos los placeholders (?,?,?)
        $placeholders = implode(',', array_fill(0, count($ids_productos), '?'));

        $sql = "
        SELECT
            lp.id_producto,
            pr.stock_minimo_base,
            SUM(lp.stock_actual_base) AS stock_total_base
        FROM
            lote_producto lp
        INNER JOIN producto pr on pr.id = lp.id_producto
        WHERE
            lp.id_almacen = ? AND 
            lp.id_producto IN ($placeholders) AND 
            lp.stock_actual_base > 0 AND 
            lp.estado = 'Activo' AND
            -- no sumar stock de lotes vencidos
            (lp.fecha_vencimiento IS NULL OR DATEDIFF(lp.fecha_vencimiento, CURRENT_DATE) >= 0)
        GROUP BY
            lp.id_producto
        ";

        $params = array_merge([$id_almacen], $ids_productos);

        return DB::select($sql, $params);
    }

    /**
     * Obtiene el almacén solicitante de una solicitud de reabastecimiento
     */
    public static function get_almacen_solicitante_by_id_solicitud(int $id_solicitud_reabastecimiento)
    {
        return SolicitudReabastecimiento::where('id', $id_solicitud_reabastecimiento)
            ->value('id_almacen_solicitante');
    }

    /**
     * Obtiene el nombre de un producto
     */
    public static function get_nombre_producto(int $id_producto): ?string
    {
        return DB::table('producto')->where('id', $id_producto)->value('nombre');
    }

    /**
     * Obtiene el stock total base de un producto en un almacén
     * Envuelve a LotesProductosData::get_lotes_disponibles para cumplir con la arquitectura
     */
    public static function get_stock_total_base_por_producto(int $id_almacen, int $id_producto): float
    {
        $lotes = LotesProductosData::get_lotes_disponibles($id_almacen, [$id_producto]);
        $totalStock = 0;
        foreach ($lotes as $lote) {
            $totalStock += (float) $lote->stock_actual_base;
        }
        return $totalStock;
    }
}
