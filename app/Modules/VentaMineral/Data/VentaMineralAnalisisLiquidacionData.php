<?php

namespace App\Modules\VentaMineral\Data;

use App\Models\AnalisisLaboratorio;
use App\Models\LiquidacionMineral;
use App\Shared\Enums\VentaMineral\EstadoLiquidacionMineral;
use Illuminate\Support\Facades\DB;

/**
 * Data de los analisis de laboratorio y de las liquidaciones de mineral.
 *
 * Es el unico lugar con SQL de esa parte del arbol.
 */
class VentaMineralAnalisisLiquidacionData
{
    /**
     * Analisis de un despacho.
     */
    public static function get_analisis(int $id_despacho_mineral): array
    {
        $sql = '
        SELECT
            a.id AS id_analisis_laboratorio,
            a.id_lote_mineral,
            a.id_despacho_mineral,
            a.id_comprobante_analisis_despacho,
            a.id_laboratorio,
            a.id_cliente,
            a.id_empleado_registro,
            a.asumido_por,
            a.codigo_laboratorio,
            a.codigo_cliente,
            a.fecha_analisis,
            a.evidencias,
            a.porcentaje_humedad,
            a.ley_oro,
            a.ley_plata,
            a.ley_cobre,
            a.ley_zinc,
            a.ley_arsenico,
            a.costo_servicio,
            a.created_at,
            lab.razon_social AS laboratorio,
            lab.ruc AS laboratorio_ruc,
            cli.razon_social AS cliente,
            CONCAT(e.nombre, " ", COALESCE(e.apellido, "")) AS empleado_registro
        FROM analisis_laboratorio a
        LEFT JOIN laboratorio lab ON lab.id = a.id_laboratorio
        LEFT JOIN cliente cli ON cli.id = a.id_cliente
        LEFT JOIN empleado e ON e.id = a.id_empleado_registro
        WHERE a.id_despacho_mineral = :id_despacho_mineral
        ORDER BY a.id ASC
        ';

        $rows = DB::select($sql, ['id_despacho_mineral' => $id_despacho_mineral]);

        foreach ($rows as $row) {
            $row->id_analisis_laboratorio = (int) $row->id_analisis_laboratorio;
            $row->id_comprobante_analisis_despacho = $row->id_comprobante_analisis_despacho !== null
                ? (int) $row->id_comprobante_analisis_despacho
                : null;
            $row->id_laboratorio = $row->id_laboratorio !== null ? (int) $row->id_laboratorio : null;
            $row->evidencias = VentaMineralData::decode_json($row->evidencias ?? null);
        }

        return $rows;
    }

    /**
     * Un analisis de laboratorio.
     */
    public static function get_analisis_by_id(int $id_analisis_laboratorio): ?object
    {
        $row = DB::table('analisis_laboratorio')
            ->where('id', $id_analisis_laboratorio)
            ->first();

        if ($row === null) {
            return null;
        }

        $row->id_analisis_laboratorio = (int) $row->id;
        $row->id_comprobante_analisis_despacho = $row->id_comprobante_analisis_despacho !== null
            ? (int) $row->id_comprobante_analisis_despacho
            : null;
        $row->evidencias = VentaMineralData::decode_json($row->evidencias ?? null);

        return $row;
    }

    /**
     * Registra un analisis de laboratorio.
     *
     * @param array<string, mixed> $d
     */
    public static function registrar_analisis(array $d): int
    {
        return AnalisisLaboratorio::insertGetId([
            'id_lote_mineral' => isset($d['id_lote_mineral']) && (int) $d['id_lote_mineral'] > 0
                ? (int) $d['id_lote_mineral']
                : null,
            'id_despacho_mineral' => (int) $d['id_despacho_mineral'],
            'id_comprobante_analisis_despacho' => null,
            'id_laboratorio' => isset($d['id_laboratorio']) && (int) $d['id_laboratorio'] > 0
                ? (int) $d['id_laboratorio']
                : null,
            'id_cliente' => isset($d['id_cliente']) && (int) $d['id_cliente'] > 0
                ? (int) $d['id_cliente']
                : null,
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'asumido_por' => (string) $d['asumido_por'],
            'codigo_laboratorio' => isset($d['codigo_laboratorio']) && $d['codigo_laboratorio'] !== ''
                ? (string) $d['codigo_laboratorio']
                : null,
            'codigo_cliente' => isset($d['codigo_cliente']) && $d['codigo_cliente'] !== ''
                ? (string) $d['codigo_cliente']
                : null,
            'fecha_analisis' => isset($d['fecha_analisis']) && $d['fecha_analisis'] !== ''
                ? (string) $d['fecha_analisis']
                : null,
            'evidencias' => VentaMineralData::encode_json($d['evidencias'] ?? null),
            'porcentaje_humedad' => (float) ($d['porcentaje_humedad'] ?? 0),
            'ley_oro' => (float) ($d['ley_oro'] ?? 0),
            'ley_plata' => (float) ($d['ley_plata'] ?? 0),
            'ley_cobre' => (float) ($d['ley_cobre'] ?? 0),
            'ley_zinc' => (float) ($d['ley_zinc'] ?? 0),
            'ley_arsenico' => (float) ($d['ley_arsenico'] ?? 0),
            'costo_servicio' => round((float) ($d['costo_servicio'] ?? 0), 2),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Actualiza un analisis de laboratorio.
     *
     * @param array<string, mixed> $d
     */
    public static function actualizar_analisis(array $d): void
    {
        $actualizaciones = [
            'id_laboratorio' => isset($d['id_laboratorio']) && (int) $d['id_laboratorio'] > 0
                ? (int) $d['id_laboratorio']
                : null,
            'id_cliente' => isset($d['id_cliente']) && (int) $d['id_cliente'] > 0
                ? (int) $d['id_cliente']
                : null,
            'asumido_por' => (string) $d['asumido_por'],
            'codigo_laboratorio' => isset($d['codigo_laboratorio']) && $d['codigo_laboratorio'] !== ''
                ? (string) $d['codigo_laboratorio']
                : null,
            'codigo_cliente' => isset($d['codigo_cliente']) && $d['codigo_cliente'] !== ''
                ? (string) $d['codigo_cliente']
                : null,
            'fecha_analisis' => isset($d['fecha_analisis']) && $d['fecha_analisis'] !== ''
                ? (string) $d['fecha_analisis']
                : null,
            'porcentaje_humedad' => (float) ($d['porcentaje_humedad'] ?? 0),
            'ley_oro' => (float) ($d['ley_oro'] ?? 0),
            'ley_plata' => (float) ($d['ley_plata'] ?? 0),
            'ley_cobre' => (float) ($d['ley_cobre'] ?? 0),
            'ley_zinc' => (float) ($d['ley_zinc'] ?? 0),
            'ley_arsenico' => (float) ($d['ley_arsenico'] ?? 0),
            'costo_servicio' => round((float) ($d['costo_servicio'] ?? 0), 2),
        ];

        if (array_key_exists('evidencias', $d)) {
            $actualizaciones['evidencias'] = VentaMineralData::encode_json($d['evidencias'] ?? null);
        }

        DB::table('analisis_laboratorio')
            ->where('id', (int) $d['id_analisis_laboratorio'])
            ->update($actualizaciones);
    }

    /**
     * Elimina un analisis de laboratorio.
     */
    public static function eliminar_analisis(int $id_analisis_laboratorio): void
    {
        DB::table('analisis_laboratorio')
            ->where('id', $id_analisis_laboratorio)
            ->delete();
    }

    /**
     * Asigna un conjunto de analisis a un comprobante de analisis.
     *
     * @param array<int, mixed> $ids_analisis
     */
    public static function asignar_analisis_comprobante(array $ids_analisis, int $id_comprobante): void
    {
        if (empty($ids_analisis)) {
            return;
        }

        DB::table('analisis_laboratorio')
            ->whereIn('id', $ids_analisis)
            ->update(['id_comprobante_analisis_despacho' => $id_comprobante]);
    }

    /**
     * Desasigna un analisis de su comprobante.
     */
    public static function desasignar_analisis(int $id_analisis_laboratorio): void
    {
        DB::table('analisis_laboratorio')
            ->where('id', $id_analisis_laboratorio)
            ->update(['id_comprobante_analisis_despacho' => null]);
    }

    /**
     * Analisis asociados a cada comprobante de analisis.
     *
     * @param array<int, mixed> $ids_comprobantes
     * @return array<int, array<object>>
     */
    public static function get_analisis_por_comprobante(array $ids_comprobantes): array
    {
        if (empty($ids_comprobantes)) {
            return [];
        }

        $rows = DB::table('analisis_laboratorio')
            ->whereIn('id_comprobante_analisis_despacho', $ids_comprobantes)
            ->orderBy('id', 'asc')
            ->get();

        $resultado = [];
        foreach ($rows as $row) {
            $row->id_analisis_laboratorio = (int) $row->id_analisis_laboratorio;
            $resultado[(int) $row->id_comprobante_analisis_despacho][] = $row;
        }

        return $resultado;
    }

    /**
     * Suma del costo de servicio de un conjunto de analisis.
     *
     * @param array<int, mixed> $ids_analisis
     */
    public static function sumar_costo_analisis(array $ids_analisis): float
    {
        if (empty($ids_analisis)) {
            return 0.0;
        }

        $total = (float) DB::table('analisis_laboratorio')
            ->whereIn('id', $ids_analisis)
            ->sum('costo_servicio');

        return round($total, 2);
    }

    /**
     * Liquidaciones de un despacho.
     */
    public static function get_liquidaciones(int $id_despacho_mineral): array
    {
        $sql = '
        SELECT
            li.id AS id_liquidacion_mineral,
            li.id_despacho_mineral,
            li.id_cliente,
            li.id_empleado_registro,
            li.codigo_liquidacion,
            li.elemento_quimico,
            li.fecha_liquidacion,
            li.peso_humedo,
            li.tara,
            li.porcentaje_humedad,
            li.peso_seco,
            li.merma,
            li.peso_neto,
            li.ley_elemento,
            li.porcentaje_recuperacion,
            li.maquila,
            li.penalidad,
            li.valor_por_tonelada,
            li.porcentaje_igv,
            li.porcentaje_detraccion,
            li.subtotal,
            li.igv,
            li.total,
            li.detraccion,
            li.neto,
            li.observaciones,
            li.created_at,
            li.estado,
            cli.razon_social AS cliente,
            CONCAT(e.nombre, " ", COALESCE(e.apellido, "")) AS empleado_registro
        FROM liquidacion_mineral li
        LEFT JOIN cliente cli ON cli.id = li.id_cliente
        LEFT JOIN empleado e ON e.id = li.id_empleado_registro
        WHERE li.id_despacho_mineral = :id_despacho_mineral
        ORDER BY li.id ASC
        ';

        $rows = DB::select($sql, ['id_despacho_mineral' => $id_despacho_mineral]);

        foreach ($rows as $row) {
            $row->id_liquidacion_mineral = (int) $row->id_liquidacion_mineral;
            $row->id_cliente = $row->id_cliente !== null ? (int) $row->id_cliente : null;
            $row->estado = $row->estado ?? EstadoLiquidacionMineral::Borrador->value;
        }

        return $rows;
    }

    /**
     * Una liquidacion de mineral.
     */
    public static function get_liquidacion(int $id_liquidacion_mineral): ?object
    {
        $row = DB::table('liquidacion_mineral')
            ->where('id', $id_liquidacion_mineral)
            ->first();

        if ($row === null) {
            return null;
        }

        $row->id_liquidacion_mineral = (int) $row->id;
        $row->id_cliente = $row->id_cliente !== null ? (int) $row->id_cliente : null;
        $row->estado = $row->estado ?? EstadoLiquidacionMineral::Borrador->value;

        return $row;
    }

    /**
     * Registra una liquidacion de mineral.
     *
     * @param array<string, mixed> $d
     */
    public static function registrar_liquidacion(array $d): int
    {
        return LiquidacionMineral::insertGetId([
            'id_despacho_mineral' => (int) $d['id_despacho_mineral'],
            'id_cliente' => (int) $d['id_cliente'],
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'codigo_liquidacion' => (string) $d['codigo_liquidacion'],
            'elemento_quimico' => (string) $d['elemento_quimico'],
            'fecha_liquidacion' => (string) $d['fecha_liquidacion'],
            'peso_humedo' => (float) ($d['peso_humedo'] ?? 0),
            'tara' => (float) ($d['tara'] ?? 0),
            'porcentaje_humedad' => (float) ($d['porcentaje_humedad'] ?? 0),
            'peso_seco' => (float) ($d['peso_seco'] ?? 0),
            'merma' => (float) ($d['merma'] ?? 0),
            'peso_neto' => (float) ($d['peso_neto'] ?? 0),
            'ley_elemento' => (float) ($d['ley_elemento'] ?? 0),
            'porcentaje_recuperacion' => (float) ($d['porcentaje_recuperacion'] ?? 0),
            'maquila' => round((float) ($d['maquila'] ?? 0), 2),
            'penalidad' => round((float) ($d['penalidad'] ?? 0), 2),
            'valor_por_tonelada' => round((float) ($d['valor_por_tonelada'] ?? 0), 2),
            'porcentaje_igv' => (float) ($d['porcentaje_igv'] ?? 0),
            'porcentaje_detraccion' => (float) ($d['porcentaje_detraccion'] ?? 0),
            'subtotal' => round((float) ($d['subtotal'] ?? 0), 2),
            'igv' => round((float) ($d['igv'] ?? 0), 2),
            'total' => round((float) ($d['total'] ?? 0), 2),
            'detraccion' => round((float) ($d['detraccion'] ?? 0), 2),
            'neto' => round((float) ($d['neto'] ?? 0), 2),
            'observaciones' => isset($d['observaciones']) && $d['observaciones'] !== ''
                ? (string) $d['observaciones']
                : null,
            'created_at' => now()->toDateTimeString(),
            'estado' => EstadoLiquidacionMineral::Borrador->value,
        ]);
    }

    /**
     * Actualiza una liquidacion de mineral.
     *
     * @param array<string, mixed> $d
     */
    public static function actualizar_liquidacion(array $d): void
    {
        $actualizaciones = [
            'id_cliente' => (int) $d['id_cliente'],
            'codigo_liquidacion' => (string) $d['codigo_liquidacion'],
            'elemento_quimico' => (string) $d['elemento_quimico'],
            'fecha_liquidacion' => (string) $d['fecha_liquidacion'],
            'peso_humedo' => (float) ($d['peso_humedo'] ?? 0),
            'tara' => (float) ($d['tara'] ?? 0),
            'porcentaje_humedad' => (float) ($d['porcentaje_humedad'] ?? 0),
            'peso_seco' => (float) ($d['peso_seco'] ?? 0),
            'merma' => (float) ($d['merma'] ?? 0),
            'peso_neto' => (float) ($d['peso_neto'] ?? 0),
            'ley_elemento' => (float) ($d['ley_elemento'] ?? 0),
            'porcentaje_recuperacion' => (float) ($d['porcentaje_recuperacion'] ?? 0),
            'maquila' => round((float) ($d['maquila'] ?? 0), 2),
            'penalidad' => round((float) ($d['penalidad'] ?? 0), 2),
            'valor_por_tonelada' => round((float) ($d['valor_por_tonelada'] ?? 0), 2),
            'porcentaje_igv' => (float) ($d['porcentaje_igv'] ?? 0),
            'porcentaje_detraccion' => (float) ($d['porcentaje_detraccion'] ?? 0),
            'subtotal' => round((float) ($d['subtotal'] ?? 0), 2),
            'igv' => round((float) ($d['igv'] ?? 0), 2),
            'total' => round((float) ($d['total'] ?? 0), 2),
            'detraccion' => round((float) ($d['detraccion'] ?? 0), 2),
            'neto' => round((float) ($d['neto'] ?? 0), 2),
            'observaciones' => isset($d['observaciones']) && $d['observaciones'] !== ''
                ? (string) $d['observaciones']
                : null,
        ];

        DB::table('liquidacion_mineral')
            ->where('id', (int) $d['id_liquidacion_mineral'])
            ->update($actualizaciones);
    }

    /**
     * Elimina una liquidacion de mineral.
     */
    public static function eliminar_liquidacion(int $id_liquidacion_mineral): void
    {
        DB::table('liquidacion_mineral')
            ->where('id', $id_liquidacion_mineral)
            ->delete();
    }

    /**
     * Actualiza el estado derivado de una liquidacion.
     */
    public static function actualizar_estado_liquidacion(int $id_liquidacion_mineral, string $estado): void
    {
        DB::table('liquidacion_mineral')
            ->where('id', $id_liquidacion_mineral)
            ->update(['estado' => $estado]);
    }

    /**
     * Liquidaciones asociadas a cada comprobante de liquidacion.
     *
     * @param array<int, mixed> $ids_comprobantes
     * @return array<int, array<object>>
     */
    public static function get_liquidaciones_por_comprobante(array $ids_comprobantes): array
    {
        if (empty($ids_comprobantes)) {
            return [];
        }

        $rows = DB::table('liquidacion_mineral')
            ->whereIn('id', $ids_comprobantes)
            ->orderBy('id', 'asc')
            ->get();

        $resultado = [];
        foreach ($rows as $row) {
            $resultado[(int) $row->id][] = $row;
        }

        return $resultado;
    }

    /**
     * Comprobante de liquidacion asociado a una liquidacion.
     */
    public static function get_comprobante_de_liquidacion(int $id_liquidacion_mineral): ?object
    {
        return DB::table('comprobante_liquidacion_despacho')
            ->where('id_liquidacion_mineral', $id_liquidacion_mineral)
            ->orderBy('id', 'asc')
            ->first();
    }
}
