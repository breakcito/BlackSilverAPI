<?php

namespace App\Modules\TamizajeCarbon\Data;

use App\Models\GastoProduccionCarbon;
use Illuminate\Support\Facades\DB;

class GastoProduccionCarbonData
{
    /**
     * Lista los gastos de producción de un zarandeo.
     *
     * @param ?int $id_gasto Si se pasa, devuelve solo ese gasto (con la misma
     *   forma que el listado) en lugar de la lista completa.
     * @return array<object>|object|null
     */
    public static function get_gastos(int $id_zarandeo_carbon, ?int $id_gasto = null)
    {
        $sql = '
            SELECT
                g.id AS id_gasto_produccion_carbon,
                g.id_zarandeo_carbon,
                g.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                g.id_proveedor,
                p.razon_social AS proveedor_razon_social,
                g.gasto,
                g.otro_gasto,
                g.descripcion,
                g.fecha,
                g.numero_reporte,
                g.hora_inicio,
                g.hora_fin,
                g.cantidad,
                g.precio_unitario,
                g.total,
                g.created_at
            FROM gasto_produccion_carbon g
            INNER JOIN empleado e ON e.id = g.id_empleado_registro
            LEFT JOIN proveedor p ON p.id = g.id_proveedor
            WHERE g.id_zarandeo_carbon = :id_zarandeo_carbon
        ';

        $params = ['id_zarandeo_carbon' => $id_zarandeo_carbon];

        if ($id_gasto !== null) {
            $sql .= ' AND g.id = :id_gasto';
            $params['id_gasto'] = $id_gasto;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY g.fecha ASC, g.id ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtiene un gasto de producción por su id, con la misma forma que el
     * listado (para que el front lo inserte en su lista sin volver a listar).
     */
    public static function get_gasto_by_id(int $id_zarandeo_carbon, int $id_gasto)
    {
        return self::get_gastos($id_zarandeo_carbon, $id_gasto);
    }

    /**
     * Verifica que el zarandeo exista.
     */
    public static function existe_zarandeo(int $id_zarandeo_carbon): bool
    {
        return DB::table('zarandeo_carbon')
            ->where('id', $id_zarandeo_carbon)
            ->exists();
    }

    /**
     * Inserta un gasto de producción.
     * @param array<string, mixed> $d
     */
    public static function insert_gasto(array $d): int
    {
        return GastoProduccionCarbon::insertGetId([
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'id_zarandeo_carbon' => (int) $d['id_zarandeo_carbon'],
            'id_proveedor' => isset($d['id_proveedor']) && (int) $d['id_proveedor'] > 0
                ? (int) $d['id_proveedor']
                : null,
            'gasto' => (string) $d['gasto'],
            'otro_gasto' => isset($d['otro_gasto']) && $d['otro_gasto'] !== ''
                ? (string) $d['otro_gasto']
                : null,
            'descripcion' => isset($d['descripcion']) && $d['descripcion'] !== ''
                ? (string) $d['descripcion']
                : null,
            'fecha' => (string) $d['fecha'],
            'numero_reporte' => isset($d['numero_reporte']) && $d['numero_reporte'] !== ''
                ? (string) $d['numero_reporte']
                : null,
            'hora_inicio' => isset($d['hora_inicio']) && $d['hora_inicio'] !== null && $d['hora_inicio'] !== ''
                ? (string) $d['hora_inicio']
                : null,
            'hora_fin' => isset($d['hora_fin']) && $d['hora_fin'] !== null && $d['hora_fin'] !== ''
                ? (string) $d['hora_fin']
                : null,
            'cantidad' => (float) ($d['cantidad'] ?? 0),
            'precio_unitario' => (float) ($d['precio_unitario'] ?? 0),
            'total' => (float) ($d['total'] ?? 0),
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}
