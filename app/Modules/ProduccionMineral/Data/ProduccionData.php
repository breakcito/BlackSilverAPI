<?php

namespace App\Modules\ProduccionMineral\Data;

use App\Models\LoteMineral;
use App\Shared\Enums\LoteMineral\EstadoLoteMineral;
use Illuminate\Support\Facades\DB;

class ProduccionData
{
    /**
     * Iniciar el proceso de producción de un lote mineral.
     * Guarda también la fecha de inicio y el código interno generado.
     */
    public static function iniciar_produccion(
        int $id_lote_mineral,
        string $inicio_produccion,
        ?string $codigo_interno
    ): bool {
        return LoteMineral::where('id', $id_lote_mineral)
            ->update([
                'estado'            => EstadoLoteMineral::EnProduccion->value,
                'inicio_produccion' => $inicio_produccion,
                'codigo_interno'    => $codigo_interno,
            ]) > 0;
    }

    /**
     * Obtener el prefijo de la labor vinculada al lote mineral.
     * Retorna null si el lote no tiene labor o la labor no tiene prefijo.
     */
    public static function get_prefijo_labor_by_lote(int $id_lote_mineral): ?string
    {
        return DB::table('lote_mineral as lm')
            ->leftJoin('labor as l', 'l.id', '=', 'lm.id_labor')
            ->where('lm.id', $id_lote_mineral)
            ->value('l.prefijo');
    }

    /**
     * Obtener listado de lotes minerales en producción y finalizados.
     */
    public static function get_lotes_en_produccion(): array
    {
        $sql = "
        SELECT
            lm.id AS id_lote_mineral,
            lm.correlativo,
            lm.codigo_interno,
            lm.inicio_produccion,
            lm.descripcion,
            lm.created_at,
            lm.estado,
            lm.id_contratista,
            CONCAT(c.nombre, ' ', c.apellido) AS contratista,
            lm.id_mina,
            m.nombre AS mina,
            lm.id_labor,
            l.nombre AS labor,
            l.prefijo AS labor_prefijo
        FROM lote_mineral lm
        LEFT JOIN empleado c ON c.id = lm.id_contratista
        LEFT JOIN mina m ON m.id = lm.id_mina
        LEFT JOIN labor l ON l.id = lm.id_labor
        WHERE lm.estado IN (:estado_en_produccion, :estado_finalizado)
        ORDER BY lm.estado DESC, lm.created_at DESC
        ";

        return DB::select($sql, [
            'estado_en_produccion' => EstadoLoteMineral::EnProduccion->value,
            'estado_finalizado' => EstadoLoteMineral::Finalizado->value,
        ]);
    }

    /**
     * Obtener consumos por lote mineral agrupados por fecha.
     */
    public static function get_consumos_by_lote_mineral(int $id_lote_mineral): array
    {
        $sql = "
        SELECT
            DATE(c.fecha_hora_consumo) AS fecha_consumo,
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
        GROUP BY DATE(c.fecha_hora_consumo), p.id, p.nombre, uni.abreviatura
        ORDER BY DATE(c.fecha_hora_consumo) DESC, p.nombre ASC
        ";

        return DB::select($sql, [
            'id_lote_mineral' => $id_lote_mineral,
        ]);
    }

    /**
     * Finalizar la producción de un lote mineral.
     */
    public static function finalizar_produccion(int $id_lote_mineral): bool
    {
        return LoteMineral::where('id', $id_lote_mineral)
            ->update([
                'estado' => EstadoLoteMineral::Finalizado->value,
            ]) > 0;
    }
}
