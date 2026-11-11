<?php

namespace App\Modules\VentaMineral\Data;

use App\Models\FleteDespacho;
use App\Models\GastoDespacho;
use Illuminate\Support\Facades\DB;

/**
 * Data de los gastos y los fletes de un despacho de mineral.
 *
 * Es el unico lugar con SQL de esa parte del arbol.
 */
class VentaMineralGastoFleteData
{
    /**
     * Gastos de un despacho.
     */
    public static function get_gastos(int $id_despacho_mineral): array
    {
        $sql = '
        SELECT
            g.id AS id_gasto_despacho,
            g.id_despacho_mineral,
            g.id_empleado_registro,
            g.tipo_gasto,
            g.descripcion,
            g.monto,
            g.created_at,
            CONCAT(e.nombre, " ", COALESCE(e.apellido, "")) AS empleado_registro
        FROM gasto_despacho g
        LEFT JOIN empleado e ON e.id = g.id_empleado_registro
        WHERE g.id_despacho_mineral = :id_despacho_mineral
        ORDER BY g.id ASC
        ';

        $rows = DB::select($sql, ['id_despacho_mineral' => $id_despacho_mineral]);

        foreach ($rows as $row) {
            $row->id_gasto_despacho = (int) $row->id_gasto_despacho;
            $row->monto = (float) $row->monto;
        }

        return $rows;
    }

    /**
     * Un gasto.
     */
    public static function get_gasto(int $id_gasto_despacho): ?object
    {
        $row = DB::table('gasto_despacho')
            ->where('id', $id_gasto_despacho)
            ->first();

        if ($row === null) {
            return null;
        }

        $row->id_gasto_despacho = (int) $row->id;
        $row->monto = (float) $row->monto;

        return $row;
    }

    /**
     * Registra un gasto del despacho.
     *
     * @param array<string, mixed> $d
     */
    public static function registrar_gasto(array $d): int
    {
        return GastoDespacho::insertGetId([
            'id_despacho_mineral' => (int) $d['id_despacho_mineral'],
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'tipo_gasto' => (string) $d['tipo_gasto'],
            'descripcion' => isset($d['descripcion']) && $d['descripcion'] !== ''
                ? (string) $d['descripcion']
                : null,
            'monto' => round((float) $d['monto'], 2),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Actualiza un gasto del despacho.
     *
     * @param array<string, mixed> $d
     */
    public static function actualizar_gasto(array $d): void
    {
        $actualizaciones = [
            'tipo_gasto' => (string) $d['tipo_gasto'],
            'monto' => round((float) $d['monto'], 2),
        ];

        if (array_key_exists('descripcion', $d)) {
            $actualizaciones['descripcion'] = isset($d['descripcion']) && $d['descripcion'] !== ''
                ? (string) $d['descripcion']
                : null;
        }

        DB::table('gasto_despacho')
            ->where('id', (int) $d['id_gasto_despacho'])
            ->update($actualizaciones);
    }

    /**
     * Elimina un gasto del despacho.
     */
    public static function eliminar_gasto(int $id_gasto_despacho): void
    {
        DB::table('gasto_despacho')
            ->where('id', $id_gasto_despacho)
            ->delete();
    }

    /**
     * Fletes de un despacho, con el transportista y su comprobante.
     */
    public static function get_fletes(int $id_despacho_mineral): array
    {
        $sql = '
        SELECT
            f.id AS id_flete_despacho,
            f.id_despacho_mineral,
            f.id_transportista_mineral,
            f.id_comprobante_flete_despacho,
            f.id_empleado_registro,
            f.placa,
            f.licencia_conductor,
            f.guia_remitente,
            f.peso_guia,
            f.codigo_ticket_balanza,
            f.peso_bruto,
            f.tara,
            f.peso_neto,
            f.fecha_hora_pesaje,
            f.largo,
            f.ancho,
            f.alto,
            f.evidencias,
            f.created_at,
            t.razon_social AS transportista,
            t.ruc AS transportista_ruc,
            CONCAT(e.nombre, " ", COALESCE(e.apellido, "")) AS empleado_registro
        FROM flete_despacho f
        LEFT JOIN transportista_mineral t ON t.id = f.id_transportista_mineral
        LEFT JOIN empleado e ON e.id = f.id_empleado_registro
        WHERE f.id_despacho_mineral = :id_despacho_mineral
        ORDER BY f.id ASC
        ';

        $rows = DB::select($sql, ['id_despacho_mineral' => $id_despacho_mineral]);

        foreach ($rows as $row) {
            $row->id_flete_despacho = (int) $row->id_flete_despacho;
            $row->id_comprobante_flete_despacho = $row->id_comprobante_flete_despacho !== null
                ? (int) $row->id_comprobante_flete_despacho
                : null;
            $row->evidencias = VentaMineralData::decode_json($row->evidencias ?? null);
        }

        return $rows;
    }

    /**
     * Un flete.
     */
    public static function get_flete(int $id_flete_despacho): ?object
    {
        $row = DB::table('flete_despacho')
            ->where('id', $id_flete_despacho)
            ->first();

        if ($row === null) {
            return null;
        }

        $row->id_flete_despacho = (int) $row->id;
        $row->id_comprobante_flete_despacho = $row->id_comprobante_flete_despacho !== null
            ? (int) $row->id_comprobante_flete_despacho
            : null;
        $row->evidencias = VentaMineralData::decode_json($row->evidencias ?? null);

        return $row;
    }

    /**
     * Registra un flete del despacho.
     *
     * @param array<string, mixed> $d
     */
    public static function registrar_flete(array $d): int
    {
        return FleteDespacho::insertGetId([
            'id_despacho_mineral' => (int) $d['id_despacho_mineral'],
            'id_transportista_mineral' => (int) $d['id_transportista_mineral'],
            'id_comprobante_flete_despacho' => null,
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'placa' => isset($d['placa']) && $d['placa'] !== '' ? (string) $d['placa'] : null,
            'licencia_conductor' => isset($d['licencia_conductor']) && $d['licencia_conductor'] !== ''
                ? (string) $d['licencia_conductor']
                : null,
            'guia_remitente' => isset($d['guia_remitente']) && $d['guia_remitente'] !== ''
                ? (string) $d['guia_remitente']
                : null,
            'peso_guia' => (float) ($d['peso_guia'] ?? 0),
            // La balanza se registra despues: el flete nace sin ella.
            'codigo_ticket_balanza' => null,
            'peso_bruto' => 0,
            'tara' => 0,
            'peso_neto' => 0,
            'fecha_hora_pesaje' => null,
            'largo' => (float) ($d['largo'] ?? 0),
            'ancho' => (float) ($d['ancho'] ?? 0),
            'alto' => (float) ($d['alto'] ?? 0),
            'evidencias' => VentaMineralData::encode_json($d['evidencias'] ?? null),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Actualiza un flete del despacho.
     *
     * @param array<string, mixed> $d
     */
    public static function actualizar_flete(array $d): void
    {
        $actualizaciones = [
            'id_transportista_mineral' => (int) $d['id_transportista_mineral'],
            'placa' => isset($d['placa']) && $d['placa'] !== '' ? (string) $d['placa'] : null,
            'licencia_conductor' => isset($d['licencia_conductor']) && $d['licencia_conductor'] !== ''
                ? (string) $d['licencia_conductor']
                : null,
            'guia_remitente' => isset($d['guia_remitente']) && $d['guia_remitente'] !== ''
                ? (string) $d['guia_remitente']
                : null,
            'peso_guia' => (float) ($d['peso_guia'] ?? 0),
            // La balanza se registra despues: el flete nace sin ella.
            'codigo_ticket_balanza' => null,
            'peso_bruto' => 0,
            'tara' => 0,
            'peso_neto' => 0,
            'fecha_hora_pesaje' => null,
            'largo' => (float) ($d['largo'] ?? 0),
            'ancho' => (float) ($d['ancho'] ?? 0),
            'alto' => (float) ($d['alto'] ?? 0),
        ];

        if (array_key_exists('evidencias', $d)) {
            $actualizaciones['evidencias'] = VentaMineralData::encode_json($d['evidencias'] ?? null);
        }

        DB::table('flete_despacho')
            ->where('id', (int) $d['id_flete_despacho'])
            ->update($actualizaciones);
    }

    /**
     * Registra los datos de balanza de un flete.
     *
     * @param array<string, mixed> $d
     */
    public static function actualizar_balanza(array $d): void
    {
        $actualizaciones = [
            'codigo_ticket_balanza' => isset($d['codigo_ticket_balanza']) && $d['codigo_ticket_balanza'] !== ''
                ? (string) $d['codigo_ticket_balanza']
                : null,
            'peso_bruto' => (float) ($d['peso_bruto'] ?? 0),
            'tara' => (float) ($d['tara'] ?? 0),
            'peso_neto' => (float) ($d['peso_neto'] ?? 0),
            'fecha_hora_pesaje' => isset($d['fecha_hora_pesaje']) && $d['fecha_hora_pesaje'] !== ''
                ? (string) $d['fecha_hora_pesaje']
                : null,
        ];

        if (array_key_exists('evidencias', $d)) {
            $actualizaciones['evidencias'] = VentaMineralData::encode_json($d['evidencias'] ?? null);
        }

        DB::table('flete_despacho')
            ->where('id', (int) $d['id_flete_despacho'])
            ->update($actualizaciones);
    }

    /**
     * Elimina un flete del despacho.
     */
    public static function eliminar_flete(int $id_flete_despacho): void
    {
        DB::table('flete_despacho')
            ->where('id', $id_flete_despacho)
            ->delete();
    }

    /**
     * Desasigna un flete de su comprobante de flete.
     */
    public static function desasignar_flete(int $id_flete_despacho): void
    {
        DB::table('flete_despacho')
            ->where('id', $id_flete_despacho)
            ->update(['id_comprobante_flete_despacho' => null]);
    }

    /**
     * Asigna un conjunto de fletes a un comprobante de flete.
     *
     * @param array<int, mixed> $ids_fletes
     */
    public static function asignar_fletes_comprobante(array $ids_fletes, int $id_comprobante): void
    {
        if (empty($ids_fletes)) {
            return;
        }

        DB::table('flete_despacho')
            ->whereIn('id', $ids_fletes)
            ->update(['id_comprobante_flete_despacho' => $id_comprobante]);
    }

    /**
     * Fletes asociados a cada comprobante de flete.
     *
     * @param array<int, mixed> $ids_comprobantes
     * @return array<int, array<object>> Indexado por id de comprobante.
     */
    public static function get_fletes_por_comprobante(array $ids_comprobantes): array
    {
        if (empty($ids_comprobantes)) {
            return [];
        }

        $rows = DB::table('flete_despacho')
            ->whereIn('id_comprobante_flete_despacho', $ids_comprobantes)
            ->orderBy('id', 'asc')
            ->get();

        $resultado = [];
        foreach ($rows as $row) {
            $row->id = (int) $row->id;
            $resultado[(int) $row->id_comprobante_flete_despacho][] = $row;
        }

        return $resultado;
    }

    /**
     * Suma de los pesos de un conjunto de fletes.
     *
     * Usa el peso neto de balanza cuando existe; si no, cae al peso de la
     * guia (que es el dato que siempre se digita).
     *
     * @param array<int, mixed> $ids_fletes
     * @return array{toneladas_neto: float, toneladas_guia: float}
     */
    public static function sumar_pesos_fletes(array $ids_fletes): array
    {
        if (empty($ids_fletes)) {
            return ['toneladas_neto' => 0.0, 'toneladas_guia' => 0.0];
        }

        $rows = DB::table('flete_despacho')
            ->whereIn('id', $ids_fletes)
            ->get();

        $toneladasNeto = 0.0;
        $toneladasGuia = 0.0;

        foreach ($rows as $row) {
            $toneladasGuia += (float) $row->peso_guia;

            $pesoNeto = (float) $row->peso_neto;
            if ($pesoNeto > 0) {
                $toneladasNeto += $pesoNeto;
            } else {
                $toneladasNeto += (float) $row->peso_guia;
            }
        }

        return [
            'toneladas_neto' => round($toneladasNeto, 6),
            'toneladas_guia' => round($toneladasGuia, 6),
        ];
    }
}
