<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Data;

use App\Data\LotesProductosData;
use App\Data\ProductosData;
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
            alm.nombre,
            alm.es_principal,
            CASE
            	WHEN EXISTS(
                    SELECT 1 FROM almacen_vecino vc
                    WHERE 
                    	(vc.id_almacen_a = 4 OR
                    	vc.id_almacen_b = 4) AND
                    	(vc.id_almacen_a = alm.id OR
                         vc.id_almacen_b = alm.id)
                ) THEN 1
                ELSE 0
            END AS es_vecino
        FROM
            almacen alm
        INNER JOIN (
            SELECT
                id_almacen,
                id_producto
            FROM
                lote_producto
            WHERE
                estado = 'Activo' AND
                stock_actual_base > 0 AND
                (fecha_vencimiento > NOW() OR fecha_vencimiento IS NULL)

            UNION ALL

            SELECT
                id_almacen,
                id_producto
            FROM
                activo_fijo
            WHERE
                estado = 'En Almacén'
        ) lot ON lot.id_almacen = alm.id
        WHERE
            alm.es_principal = 0 AND 
            alm.estado = 'Activo' AND
            alm.id != ? AND
            lot.id_producto IN ($placeholders)
        GROUP BY
            alm.id,
            alm.nombre
        HAVING
            COUNT(DISTINCT lot.id_producto) = ?;
        ";

        // Unimos los bindings: id_excluido, ids de productos, y el total al final para el HAVING
        $bindings = array_merge([$id_almacen_excluido], $ids_unicos, [$totalIds]);

        return DB::select($sql, $bindings);
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
}
