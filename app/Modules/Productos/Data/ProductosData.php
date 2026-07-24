<?php

namespace App\Modules\Productos\Data;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ProductosData
{
    /**
     * Listar todos los productos del catálogo con su categoría y unidad de medida
     */
    public static function get_productos(?int $id_producto = null)
    {
        $sql = '
            SELECT
                p.id AS id_producto,
                p.nombre,
                -- 
                p.id_categoria,
                c.nombre as categoria,
                c.clasificacion_bien,
                --
                p.id_unidad_medida_base,
                um.nombre as unidad_medida_base,
                um.abreviatura as unidad_medida_base_abreviatura,
                -- 
                p.prefijo,
                -- 
                p.es_auditable,
                p.es_perecible,
                p.para_mantenimiento,
                -- 
                p.stock_minimo_base,
                p.costo_promedio_base,
                p.costo_promedio_base_log,
                -- 
                p.tiempo_espera_vencimiento,
                p.periodo_espera_vencimiento,
                p.dias_espera_vencimiento,
                -- 
                p.estado
            FROM
                producto p
            INNER JOIN categoria c ON c.id = p.id_categoria
            INNER JOIN unidad_medida um ON um.id = p.id_unidad_medida_base
            WHERE
                1 = 1
        ';

        $params = [];
        if ($id_producto !== null) {
            $sql .= ' AND p.id = :id_producto';
            $params['id_producto'] = $id_producto;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' AND p.estado != :estado_inactivo ORDER BY p.nombre ASC';
        $params['estado_inactivo'] = EstadoBase::Inactivo->value;

        return DB::select($sql, $params);
    }

    /**
     * Actualizar un producto existente manteniendo inalterable su historial de costos
     */
    public static function actualizar_producto(
        int $id_producto,
        int $id_categoria,
        int $id_unidad_medida_base,
        string $nombre,
        bool $es_auditable,
        bool $es_perecible,
        bool $para_mantenimiento,
        float $stock_minimo_base,
        float $costo_promedio_base,
        ?string $prefijo = null,
        ?int $tiempo_espera_vencimiento = null,
        ?string $periodo_espera_vencimiento = null,
        ?int $dias_espera_vencimiento = null
    ): int {
        $affected = DB::table('producto')
            ->where('id', $id_producto)
            ->update([
                'id_categoria' => $id_categoria,
                'id_unidad_medida_base' => $id_unidad_medida_base,
                'nombre' => $nombre,
                'prefijo' => $prefijo,
                'es_auditable' => $es_auditable,
                'es_perecible' => $es_perecible,
                'para_mantenimiento' => $para_mantenimiento,
                'stock_minimo_base' => $stock_minimo_base,
                'costo_promedio_base' => $costo_promedio_base,
                'tiempo_espera_vencimiento' => $tiempo_espera_vencimiento,
                'periodo_espera_vencimiento' => $periodo_espera_vencimiento,
                'dias_espera_vencimiento' => $dias_espera_vencimiento,
            ]);

        return (int) $affected;
    }

    /**
     * Desactivar (soft delete) un producto cambiando su estado a Inactivo.
     * No se elimina físicamente para preservar la integridad referencial con Kardex y Lotes.
     */
    public static function eliminar_producto(int $id_producto): int
    {
        $affected = DB::table('producto')
            ->where('id', $id_producto)
            ->update([
                'estado' => EstadoBase::Inactivo->value,
            ]);

        return (int) $affected;
    }

    /**
     * Verificar si ya existe un producto activo con el mismo nombre, excluyendo opcionalmente un ID concreto
     */
    public static function existe_nombre(string $nombre, ?int $excluir_id = null): bool
    {
        $query = DB::table('producto')
            ->where('nombre', $nombre)
            ->where('estado', '!=', EstadoBase::Inactivo->value);

        if ($excluir_id !== null) {
            $query->where('id', '!=', $excluir_id);
        }

        return $query->exists();
    }
}
