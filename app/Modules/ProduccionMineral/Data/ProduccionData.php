<?php

namespace App\Modules\ProduccionMineral\Data;

use App\Models\LoteMineral;
use App\Shared\Enums\LoteMineral\EstadoLoteMineral;
use Illuminate\Support\Facades\DB;

class ProduccionData
{
    /**
     * Iniciar el proceso de producción de un lote mineral.
     */
    public static function iniciar_produccion(int $id_lote_mineral): bool
    {
        return LoteMineral::where('id', $id_lote_mineral)
            ->update([
                'estado' => EstadoLoteMineral::EnProduccion->value,
            ]) > 0;
    }

    /**
     * Obtener listado de lotes minerales que están en producción.
     */
    public static function get_lotes_en_produccion(): array
    {
        $sql = "
        SELECT
            lm.id AS id_lote_mineral,
            lm.correlativo,
            lm.codigo_interno,
            lm.descripcion,
            lm.created_at,
            lm.id_contratista,
            CONCAT(c.nombre, ' ', c.apellido) AS contratista,
            lm.id_mina,
            m.nombre AS mina,
            lm.id_labor,
            l.nombre AS labor
        FROM lote_mineral lm
        LEFT JOIN empleado c ON c.id = lm.id_contratista
        LEFT JOIN mina m ON m.id = lm.id_mina
        LEFT JOIN labor l ON l.id = lm.id_labor
        WHERE lm.estado = :estado
        ORDER BY lm.created_at DESC
        ";

        return DB::select($sql, [
            'estado' => EstadoLoteMineral::EnProduccion->value,
        ]);
    }

    /**
     * Obtener el resumen consolidado de productos consumidos asociados a un lote mineral.
     */
    public static function get_consumos_by_lote_mineral(int $id_lote_mineral): array
    {
        $sql = "
        SELECT
            p.id AS id_producto,
            p.nombre AS producto,
            SUM(c.cantidad_base_consumida) AS total_consumido,
            uni.abreviatura AS unidad_base_abv
        FROM requerimiento_almacen_entrega_detalle_consumo c
        INNER JOIN requerimiento_almacen_entrega_detalle raed ON raed.id = c.id_requerimiento_almacen_entrega_detalle
        LEFT JOIN lote_producto lot ON lot.id = raed.id_lote_producto
        LEFT JOIN activo_fijo act ON act.id = raed.id_activo_fijo
        INNER JOIN producto p ON p.id = COALESCE(lot.id_producto, act.id_producto)
        INNER JOIN unidad_medida uni ON uni.id = p.id_unidad_medida_base
        WHERE c.id_lote_mineral = :id_lote_mineral
        GROUP BY p.id, p.nombre, uni.abreviatura
        ORDER BY p.nombre ASC
        ";

        return DB::select($sql, [
            'id_lote_mineral' => $id_lote_mineral,
        ]);
    }
}
