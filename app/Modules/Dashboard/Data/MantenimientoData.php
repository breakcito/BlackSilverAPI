<?php

namespace App\Modules\Dashboard\Data;

use Illuminate\Support\Facades\DB;

/**
 * Indicadores 2 y 9: activos y mantenimiento.
 *
 * - 2: alertas de mantenimiento proximo y vencido (maquinaria con control por
 *      horometro, odometro o vueltas).
 * - 9: costo de mantenimiento del periodo y su composicion (mano de obra,
 *      otros gastos e insumos consumidos).
 *
 * No existe el concepto "maquinaria" en el sistema: el universo es todo
 * `activo_fijo` cuya categoria declara al menos un control de uso, porque
 * es la categoria la que define contra que se mide el mantenimiento.
 */
class MantenimientoData
{
    /** Umbral de horas que define la alerta critica de un horometro. */
    private const UMBRAL_HORAS_CRITICO = 50.0;

    /** Porcentaje de intervalo restante que dispara la alerta critica. */
    private const PORCENTAJE_CRITICO = 10.0;

    /** Porcentaje de intervalo restante que dispara la alerta de proximidad. */
    private const PORCENTAJE_PROXIMO = 25.0;

    /**
     * Activos con umbral de mantenimiento evaluated.
     *
     * Devuelve TODOS los activos evaluables (tambien los que estan en "OK")
     * para que el resumen pueda contar umbrales obsoletos, que son un riesgo
     * invisible: el contador se ajusto a mano y el umbral nunca se reprogramo.
     *
     * @param int|null $id_almacen Limita el resultado a un almacen.
     */
    public static function get_alertas_mantenimiento(?int $id_almacen = null): array
    {
        $sql = '
        SELECT
            af.id AS id_activo_fijo,
            af.codigo,
            af.correlativo,
            af.estado AS estado_activo,
            af.id_almacen,
            a.nombre AS almacen,
            p.nombre AS producto,
            af.total_horas,
            af.proxima_advertencia_horas,
            af.intervalo_mantenimiento_horas,
            af.total_kilometros,
            af.proxima_advertencia_kilometros,
            af.intervalo_mantenimiento_kilometros,
            af.total_vueltas,
            af.proxima_advertencia_vueltas,
            af.intervalo_mantenimiento_vueltas,
            cat.control_por_horometro,
            cat.control_por_odometro,
            cat.control_por_vueltas
        FROM activo_fijo af
        INNER JOIN producto p ON p.id = af.id_producto
        INNER JOIN categoria cat ON cat.id = p.id_categoria
        LEFT JOIN almacen a ON a.id = af.id_almacen
        WHERE af.estado <> "Dado de Baja"
          AND af.estado <> "Eliminado"
          AND (
                cat.control_por_horometro = 1
             OR cat.control_por_odometro = 1
             OR cat.control_por_vueltas = 1
          )
          AND (
                af.proxima_advertencia_horas IS NOT NULL
             OR af.proxima_advertencia_kilometros IS NOT NULL
             OR af.proxima_advertencia_vueltas IS NOT NULL
          )
        ';

        $params = [];

        if ($id_almacen !== null) {
            $sql .= ' AND af.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        $rows = DB::select($sql, $params);

        $evaluados = [];

        foreach ($rows as $row) {
            $dimensiones = [];

            if ((bool) $row->control_por_horometro) {
                $dimensiones[] = self::evaluar_dimension(
                    $row->total_horas,
                    $row->proxima_advertencia_horas,
                    $row->intervalo_mantenimiento_horas,
                    'horas'
                );
            }

            if ((bool) $row->control_por_odometro) {
                $dimensiones[] = self::evaluar_dimension(
                    $row->total_kilometros,
                    $row->proxima_advertencia_kilometros,
                    $row->intervalo_mantenimiento_kilometros,
                    'kilometros'
                );
            }

            if ((bool) $row->control_por_vueltas) {
                $dimensiones[] = self::evaluar_dimension(
                    $row->total_vueltas,
                    $row->proxima_advertencia_vueltas,
                    $row->intervalo_mantenimiento_vueltas,
                    'vueltas'
                );
            }

            $dimensiones = array_values(array_filter($dimensiones));

            if (empty($dimensiones)) {
                continue;
            }

            // Un activo puede controlar mas de una dimension: se reporta la
            // mas urgente. El porcentaje restante es comparable entre
            // dimensiones, a diferencia del valor absoluto.
            usort($dimensiones, fn (array $a, array $b) => $a['porcentaje_restante'] <=> $b['porcentaje_restante']);
            $principal = $dimensiones[0];

            $evaluados[] = [
                'id_activo_fijo' => (int) $row->id_activo_fijo,
                'codigo' => $row->codigo !== null ? (string) $row->codigo : null,
                'correlativo' => $row->correlativo !== null ? (string) $row->correlativo : null,
                'producto' => (string) $row->producto,
                'estado_activo' => (string) $row->estado_activo,
                'id_almacen' => $row->id_almacen !== null ? (int) $row->id_almacen : null,
                'almacen' => $row->almacen !== null ? (string) $row->almacen : null,
                'dimension' => $principal['dimension'],
                'total' => $principal['total'],
                'umbral' => $principal['umbral'],
                'intervalo' => $principal['intervalo'],
                'restantes' => $principal['restantes'],
                'porcentaje_restante' => $principal['porcentaje_restante'],
                'nivel' => $principal['nivel'],
                'umbral_obsoleto' => $principal['umbral_obsoleto'],
            ];
        }

        return $evaluados;
    }

    /**
     * Costo de mantenimiento del periodo con su composicion.
     *
     * El costo real ampliado suma los insumos consumidos en el
     * mantenimiento: hoy esos insumos NO forman parte del costo que se ve en
     * el modulo Mantenimiento, asi que el dashboard muestra la cifra completa.
     *
     * @param string $fecha_inicio Fecha inicial del periodo (Y-m-d).
     * @param string $fecha_fin    Fecha final del periodo (Y-m-d).
     */
    public static function get_costo_mantenimiento(string $fecha_inicio, string $fecha_fin): array
    {
        $sql = '
        SELECT
            ma.id AS id_mantenimiento,
            ma.id_activo_fijo,
            ma.fecha_hora_mantenimiento,
            ma.costo_mano_obra,
            ma.otros_gastos,
            act.correlativo AS activo_correlativo,
            prod.nombre AS activo_producto,
            COALESCE(cs.costo_insumos, 0) AS costo_insumos
        FROM mantenimiento_activo ma
        INNER JOIN activo_fijo act ON act.id = ma.id_activo_fijo
        INNER JOIN producto prod ON prod.id = act.id_producto
        LEFT JOIN (
            SELECT
                c.id_mantenimiento,
                SUM(
                    c.cantidad_base_consumida * COALESCE(
                        NULLIF(lp.costo_promedio_base, 0),
                        NULLIF(lp.costo_por_unidad, 0) / NULLIF(lp.contenido_por_presentacion, 0),
                        0
                    )
                ) AS costo_insumos
            FROM requerimiento_almacen_entrega_detalle_consumo c
            INNER JOIN requerimiento_almacen_entrega_detalle raed
                ON raed.id = c.id_requerimiento_almacen_entrega_detalle
            LEFT JOIN lote_producto lp ON lp.id = raed.id_lote_producto
            WHERE c.id_mantenimiento IS NOT NULL
              AND c.fecha_hora_consumo >= :fecha_inicio_consumo
              AND c.fecha_hora_consumo < :fecha_fin_excl_consumo
            GROUP BY c.id_mantenimiento
        ) cs ON cs.id_mantenimiento = ma.id
        WHERE ma.fecha_hora_mantenimiento >= :fecha_inicio
          AND ma.fecha_hora_mantenimiento < :fecha_fin_excl
        ORDER BY ma.fecha_hora_mantenimiento DESC
        ';

        $params = [
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin_excl' => date('Y-m-d', strtotime($fecha_fin . ' +1 day')),
            'fecha_inicio_consumo' => $fecha_inicio,
            'fecha_fin_excl_consumo' => date('Y-m-d', strtotime($fecha_fin . ' +1 day')),
        ];

        $rows = DB::select($sql, $params);

        $mantenimientos = [];

        foreach ($rows as $row) {
            $costo_mano_obra = (float) ($row->costo_mano_obra ?? 0);
            $costo_otros_gastos = self::sumar_otros_gastos($row->otros_gastos);
            $costo_insumos = (float) ($row->costo_insumos ?? 0);

            $mantenimientos[] = [
                'id_mantenimiento' => (int) $row->id_mantenimiento,
                'id_activo_fijo' => (int) $row->id_activo_fijo,
                'activo_correlativo' => $row->activo_correlativo !== null ? (string) $row->activo_correlativo : null,
                'activo_producto' => (string) $row->activo_producto,
                'fecha_hora_mantenimiento' => (string) $row->fecha_hora_mantenimiento,
                'costo_mano_obra' => round($costo_mano_obra, 2),
                'costo_otros_gastos' => round($costo_otros_gastos, 2),
                'costo_insumos' => round($costo_insumos, 2),
                'costo_total' => round($costo_mano_obra + $costo_otros_gastos + $costo_insumos, 2),
            ];
        }

        return $mantenimientos;
    }

    /**
     * Evalua una dimension de control (horas, kilometros o vueltas).
     *
     * Devuelve null cuando el activo no tiene umbral o intervalo configurado
     * en esa dimension: sin intervalo no hay forma de saber cuando tocar.
     */
    private static function evaluar_dimension(
        mixed $total,
        mixed $umbral,
        mixed $intervalo,
        string $dimension
    ): ?array {
        if ($umbral === null || $intervalo === null) {
            return null;
        }

        $total = (float) $total;
        $umbral = (float) $umbral;
        $intervalo = (float) $intervalo;

        if ($intervalo <= 0) {
            return null;
        }

        $restantes = $umbral - $total;
        $porcentaje = ($restantes / $intervalo) * 100;

        if ($restantes < 0) {
            $nivel = 'Vencido';
        } elseif ($dimension === 'horas' && $restantes <= self::UMBRAL_HORAS_CRITICO) {
            $nivel = 'Crítico';
        } elseif ($porcentaje <= self::PORCENTAJE_CRITICO) {
            $nivel = 'Crítico';
        } elseif ($porcentaje <= self::PORCENTAJE_PROXIMO) {
            $nivel = 'Próximo';
        } else {
            $nivel = 'OK';
        }

        return [
            'dimension' => $dimension,
            'total' => round($total, 2),
            'umbral' => round($umbral, 2),
            'intervalo' => round($intervalo, 2),
            'restantes' => round($restantes, 2),
            'porcentaje_restante' => round($porcentaje, 1),
            'nivel' => $nivel,
            // El umbral quedo obsoleto si el contador ya supera el intervalo
            // completo: el mantenimiento no reprogramo la advertencia.
            'umbral_obsoleto' => ($total + $intervalo) < $umbral,
        ];
    }

    /**
     * Suma el JSON de otros gastos.
     *
     * El formato guardado es una lista de {concepto, costo}, pero el campo
     * puede venir ya decodificado, como texto o como un numero suelto.
     */
    private static function sumar_otros_gastos(mixed $otros_gastos): float
    {
        if ($otros_gastos === null) {
            return 0.0;
        }

        if (is_numeric($otros_gastos)) {
            return (float) $otros_gastos;
        }

        if (is_string($otros_gastos)) {
            $decodificado = json_decode($otros_gastos, true);
            if (!is_array($decodificado)) {
                return 0.0;
            }
            $otros_gastos = $decodificado;
        }

        if (!is_array($otros_gastos)) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($otros_gastos as $gasto) {
            if (is_numeric($gasto)) {
                $total += (float) $gasto;
                continue;
            }

            if (!is_array($gasto)) {
                continue;
            }

            $costo = $gasto['costo'] ?? $gasto['valor'] ?? null;

            if ($costo !== null && is_numeric($costo)) {
                $total += (float) $costo;
            }
        }

        return $total;
    }
}
