<?php

namespace App\Modules\Dashboard\Service;

use App\Modules\Balances\Data\BalancesData;
use App\Modules\Dashboard\Data\CobrosPendientesData;
use App\Modules\Dashboard\Data\ComprasPendientesData;
use App\Modules\Dashboard\Data\MantenimientoData;
use App\Modules\Dashboard\Data\PendientesAtencionData;
use App\Modules\Dashboard\Data\StockCriticoData;
use App\Shared\Enums\Balances\TipoBalance;
use App\Shared\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard: los indicadores de la empresa en una sola lectura.
 *
 * El servicio ORQUESTA: cada indicador trae sus datos de su propia capa Data
 * y aqui se arman los resumenes que el front consume.
 *
 * Dos criterios gobiernan los filtros:
 *
 * 1. Los indicadores de FLUJO (balance, cobros y costo de mantenimiento) se
 *    miden sobre el periodo pedido.
 * 2. Los indicadores de ESTADO (stock critico, mantenimiento proximo y
 *    pendientes de atencion) son una foto del ahora: no dependen del periodo,
 *    porque un producto sin stock lo esta hoy igual que ayer. Si el filtro
 *    por periodo los alcanzara, se acumularian alertas ya resueltas.
 *
 * El filtro por almacen si aplica a todos: es un ambito, no un periodo.
 *
 * El balance reutiliza la consulta de movimientos del modulo Balances para
 * que el dashboard y ese modulo nunca se contradigan.
 */
class DashboardService
{
    /**
     * Genera todos los indicadores del dashboard.
     *
     * @param string      $fecha_inicio              Fecha inicial del periodo (Y-m-d).
     * @param string      $fecha_fin                 Fecha final del periodo (Y-m-d).
     * @param int|null    $id_almacen                Limita los indicadores a un almacen.
     * @param string|null $fecha_inicio_comparativo  Inicio del periodo de comparacion.
     * @param string|null $fecha_fin_comparativo    Fin del periodo de comparacion.
     */
    public static function get_indicadores(
        string $fecha_inicio,
        string $fecha_fin,
        ?int $id_almacen = null,
        ?string $fecha_inicio_comparativo = null,
        ?string $fecha_fin_comparativo = null
    ) {
        try {
            $inicio = Carbon::parse($fecha_inicio)->startOfDay();
            $fin = Carbon::parse($fecha_fin)->startOfDay();

            if ($fin->lt($inicio)) {
                return ApiResponse::error('La fecha final del periodo no puede ser anterior a la fecha inicial.');
            }

            // Sin comparacion explicita se compara contra el periodo
            // inmediatamente anterior y de la misma duracion.
            if (!$fecha_inicio_comparativo || !$fecha_fin_comparativo) {
                $dias = $inicio->diffInDays($fin) + 1;
                $comparativo_inicio = $inicio->copy()->subDays($dias);
                $comparativo_fin = $inicio->copy()->subDay();
            } else {
                $comparativo_inicio = Carbon::parse($fecha_inicio_comparativo)->startOfDay();
                $comparativo_fin = Carbon::parse($fecha_fin_comparativo)->startOfDay();
            }

            // Cada bloque se aísla: si un indicador falla, el resto del
            // dashboard sigue funcionando y el bloque roto lo dice.
            $stock_critico = self::bloque(
                fn () => self::armar_stock_critico($id_almacen),
                ['items' => [], 'resumen' => ['total_items' => 0, 'criticos' => 0, 'sin_stock' => 0, 'almacenes' => []]]
            );

            $mantenimiento = self::bloque(
                fn () => self::armar_mantenimiento($id_almacen),
                ['items' => [], 'resumen' => ['vencidos' => 0, 'criticos' => 0, 'proximos' => 0, 'umbrales_obsoletos' => 0, 'evaluados' => 0]]
            );

            $balance = self::bloque(
                fn () => self::armar_balance($inicio, $fin, $comparativo_inicio, $comparativo_fin),
                ['monedas' => [], 'origenes' => [], 'ambitos' => []]
            );

            $compras = self::bloque(
                fn () => self::armar_compras($id_almacen),
                ['ocs' => [], 'items' => [], 'resumen' => ['total_ocs' => 0, 'items_pendientes' => 0, 'por_moneda' => [], 'antiguedad_max_dias' => 0, 'transferencias_en_transito' => 0, 'recepciones_con_incidencia' => 0]]
            );

            $cobros = self::bloque(
                fn () => self::armar_cobros($inicio, $fin),
                ['items' => [], 'anticipos' => [], 'resumen' => ['total_comprobantes' => 0, 'por_moneda' => [], 'por_cliente' => []]]
            );

            $pendientes = self::bloque(
                fn () => self::armar_pendientes($id_almacen),
                ['etapas' => [], 'vencidos' => [], 'resumen' => ['total' => 0, 'vencidos' => 0, 'por_tipo' => []]]
            );

            $costo_mantenimiento = self::bloque(
                fn () => self::armar_costo_mantenimiento($inicio, $fin),
                ['mantenimientos' => [], 'por_activo' => [], 'resumen' => ['cantidad' => 0, 'costo_mano_obra' => 0, 'costo_otros_gastos' => 0, 'costo_insumos' => 0, 'costo_total' => 0, 'costo_promedio' => 0]]
            );

            $payload = [
                'periodo' => [
                    'fecha_inicio' => $inicio->toDateString(),
                    'fecha_fin' => $fin->toDateString(),
                    'dias' => $inicio->diffInDays($fin) + 1,
                ],
                'comparativo' => [
                    'fecha_inicio' => $comparativo_inicio->toDateString(),
                    'fecha_fin' => $comparativo_fin->toDateString(),
                    'dias' => $comparativo_inicio->diffInDays($comparativo_fin) + 1,
                ],
                'filtros' => [
                    'id_almacen' => $id_almacen,
                ],
                'alertas' => [
                    'total' => self::total_alertas($stock_critico, $mantenimiento, $compras, $cobros, $pendientes),
                    'por_bloque' => [
                        'stock_critico' => (int) $stock_critico['resumen']['total_items'],
                        'mantenimiento' => (int) $mantenimiento['resumen']['vencidos'] + (int) $mantenimiento['resumen']['criticos'],
                        'compras_pendientes' => (int) $compras['resumen']['total_ocs'],
                        'cobros_pendientes' => (int) $cobros['resumen']['total_comprobantes'],
                        'pendientes_atencion' => (int) $pendientes['resumen']['total'],
                    ],
                ],
                'stock_critico' => $stock_critico,
                'mantenimiento_alertas' => $mantenimiento,
                'balance' => $balance,
                'compras_pendientes' => $compras,
                'cobros_pendientes' => $cobros,
                'pendientes_atencion' => $pendientes,
                'costo_mantenimiento' => $costo_mantenimiento,
            ];

            return ApiResponse::success($payload, 'Indicadores del dashboard generados');
        } catch (\Throwable $e) {
            Log::error('Error al generar los indicadores del dashboard: ' . $e->getMessage());
            return ApiResponse::error('Ocurrió un error al generar los indicadores del dashboard.');
        }
    }

    /**
     * Ejecuta un bloque del dashboard aislando el fallo.
     *
     * @param callable $fn    Bloque a ejecutar.
     * @param array    $vacio  Estructura que se devuelve si el bloque falla.
     */
    private static function bloque(callable $fn, array $vacio): array
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            Log::error('Error en un bloque del dashboard: ' . $e->getMessage());
            return $vacio + ['error' => 'No se pudo calcular este indicador'];
        }
    }

    /**
     * Indicador 1: stock critico por almacen.
     */
    private static function armar_stock_critico(?int $id_almacen): array
    {
        $items = StockCriticoData::get_stock_critico($id_almacen);

        $por_almacen = [];
        $criticos = 0;
        $sin_stock = 0;

        foreach ($items as $item) {
            $id = $item['id_almacen'];

            if (!isset($por_almacen[$id])) {
                $por_almacen[$id] = [
                    'id_almacen' => $id,
                    'almacen' => $item['almacen'],
                    'items' => 0,
                    'criticos' => 0,
                    'sin_stock' => 0,
                ];
            }

            $por_almacen[$id]['items']++;

            if ($item['estado'] === 'Sin stock') {
                $por_almacen[$id]['sin_stock']++;
                $sin_stock++;
            } else {
                $por_almacen[$id]['criticos']++;
                $criticos++;
            }
        }

        return [
            'items' => $items,
            'resumen' => [
                'total_items' => count($items),
                'criticos' => $criticos,
                'sin_stock' => $sin_stock,
                'almacenes' => array_values($por_almacen),
            ],
        ];
    }

    /**
     * Indicador 2: alertas de mantenimiento proximo y vencido.
     */
    private static function armar_mantenimiento(?int $id_almacen): array
    {
        $evaluados = MantenimientoData::get_alertas_mantenimiento($id_almacen);

        $items = [];
        $vencidos = 0;
        $criticos = 0;
        $proximos = 0;
        $obsoletos = 0;

        foreach ($evaluados as $activo) {
            if ($activo['umbral_obsoleto']) {
                $obsoletos++;
            }

            if ($activo['nivel'] === 'OK') {
                continue;
            }

            if ($activo['nivel'] === 'Vencido') {
                $vencidos++;
            } elseif ($activo['nivel'] === 'Crítico') {
                $criticos++;
            } else {
                $proximos++;
            }

            $items[] = $activo;
        }

        // Lo mas urgente primero: vencido, luego critico, luego proximo.
        usort($items, function (array $a, array $b) {
            $prioridad = ['Vencido' => 0, 'Crítico' => 1, 'Próximo' => 2];
            $diferencia = $prioridad[$a['nivel']] <=> $prioridad[$b['nivel']];

            if ($diferencia !== 0) {
                return $diferencia;
            }

            return $a['porcentaje_restante'] <=> $b['porcentaje_restante'];
        });

        return [
            'items' => $items,
            'resumen' => [
                'vencidos' => $vencidos,
                'criticos' => $criticos,
                'proximos' => $proximos,
                'umbrales_obsoletos' => $obsoletos,
                'evaluados' => count($evaluados),
            ],
        ];
    }

    /**
     * Indicador 3: balance del periodo (ingresos, egresos y resultado).
     */
    private static function armar_balance(
        Carbon $inicio,
        Carbon $fin,
        Carbon $comparativo_inicio,
        Carbon $comparativo_fin
    ): array {
        // Los dos periodos se piden en UNA consulta y se separan por fecha:
        // contra una base de datos remota cada consulta de mas se paga en
        // tiempo de espera, y el rango completo es un solo viaje.
        $desde = $inicio->lt($comparativo_inicio) ? $inicio : $comparativo_inicio;
        $hasta = $fin->gt($comparativo_fin) ? $fin : $comparativo_fin;

        $movimientos = BalancesData::get_movimientos(
            $desde->toDateString(),
            $hasta->toDateString()
        );

        $inicio_actual = $inicio->toDateString();
        $fin_actual = $fin->toDateString();
        $inicio_comparativo = $comparativo_inicio->toDateString();
        $fin_comparativo = $comparativo_fin->toDateString();

        $actual = [];
        $comparativo = [];

        foreach ($movimientos as $movimiento) {
            $fecha = $movimiento['fecha'];

            if ($fecha >= $inicio_actual && $fecha <= $fin_actual) {
                $actual[] = $movimiento;
                continue;
            }

            if ($fecha >= $inicio_comparativo && $fecha <= $fin_comparativo) {
                $comparativo[] = $movimiento;
            }
        }

        $resumen_actual = self::acumular_movimientos($actual);
        $resumen_comparativo = self::acumular_movimientos($comparativo);

        $monedas = [];

        foreach (['Soles', 'Dólares'] as $nombre) {
            $a = $resumen_actual['monedas'][$nombre] ?? ['ingresos' => 0.0, 'egresos' => 0.0, 'resultado' => 0.0];
            $c = $resumen_comparativo['monedas'][$nombre] ?? ['ingresos' => 0.0, 'egresos' => 0.0, 'resultado' => 0.0];

            $monedas[] = [
                'moneda' => $nombre,
                'ingresos' => round((float) $a['ingresos'], 2),
                'egresos' => round((float) $a['egresos'], 2),
                'resultado' => round((float) $a['resultado'], 2),
                'comparativo' => [
                    'ingresos' => round((float) $c['ingresos'], 2),
                    'egresos' => round((float) $c['egresos'], 2),
                    'resultado' => round((float) $c['resultado'], 2),
                ],
            ];
        }

        $origenes = $resumen_actual['origenes'];
        usort($origenes, fn (array $a, array $b) => $b['monto'] <=> $a['monto']);

        $ambitos = [];
        foreach ($resumen_actual['ambitos'] as $ambito) {
            $ambitos[] = $ambito;
        }
        usort($ambitos, fn (array $a, array $b) => $b['resultado'] <=> $a['resultado']);

        return [
            'monedas' => $monedas,
            'origenes' => $origenes,
            'ambitos' => $ambitos,
        ];
    }

    /**
     * Agrupa movimientos del balance por moneda, origen y ambito.
     *
     * @param array<int, array<string, mixed>> $movimientos
     */
    private static function acumular_movimientos(array $movimientos): array
    {
        $monedas = [];
        $origenes = [];
        $ambitos = [];

        foreach ($movimientos as $movimiento) {
            $moneda = $movimiento['moneda'];
            $tipo = $movimiento['tipo'];
            $monto = (float) $movimiento['monto'];
            $es_ingreso = $tipo === TipoBalance::Ingreso->value;

            if (!isset($monedas[$moneda])) {
                $monedas[$moneda] = ['moneda' => $moneda, 'ingresos' => 0.0, 'egresos' => 0.0, 'resultado' => 0.0];
            }
            $monedas[$moneda][$es_ingreso ? 'ingresos' : 'egresos'] += $monto;

            $clave_origen = $movimiento['origen'] . '|' . $movimiento['ambito'] . '|' . $tipo . '|' . $moneda;
            if (!isset($origenes[$clave_origen])) {
                $origenes[$clave_origen] = [
                    'origen' => $movimiento['origen'],
                    'ambito' => $movimiento['ambito'],
                    'tipo' => $tipo,
                    'moneda' => $moneda,
                    'monto' => 0.0,
                ];
            }
            $origenes[$clave_origen]['monto'] += $monto;

            $clave_ambito = $movimiento['ambito'] . '|' . $moneda;
            if (!isset($ambitos[$clave_ambito])) {
                $ambitos[$clave_ambito] = [
                    'ambito' => $movimiento['ambito'],
                    'moneda' => $moneda,
                    'ingresos' => 0.0,
                    'egresos' => 0.0,
                    'resultado' => 0.0,
                ];
            }
            $ambitos[$clave_ambito][$es_ingreso ? 'ingresos' : 'egresos'] += $monto;
        }

        foreach ($monedas as $clave => $moneda) {
            $monedas[$clave]['resultado'] = $moneda['ingresos'] - $moneda['egresos'];
        }

        foreach ($ambitos as $clave => $ambito) {
            $ambitos[$clave]['resultado'] = $ambito['ingresos'] - $ambito['egresos'];
        }

        foreach ($origenes as $clave => $origen) {
            $origenes[$clave]['monto'] = round($origen['monto'], 2);
        }

        return [
            'monedas' => $monedas,
            'origenes' => array_values($origenes),
            'ambitos' => array_values($ambitos),
        ];
    }

    /**
     * Indicador 4: compras pendientes de recepcion.
     */
    private static function armar_compras(?int $id_almacen): array
    {
        $ocs = ComprasPendientesData::get_ocs_pendientes($id_almacen);
        $items = ComprasPendientesData::get_items_pendientes($id_almacen);
        $senales = ComprasPendientesData::get_senales_transito($id_almacen);

        $por_moneda = [];
        $items_pendientes = 0;
        $antiguedad_max = 0;

        foreach ($ocs as $oc) {
            $items_pendientes += $oc['items_pendientes'];
            $antiguedad_max = max($antiguedad_max, $oc['dias_antiguedad']);

            $moneda = $oc['moneda'];
            if (!isset($por_moneda[$moneda])) {
                $por_moneda[$moneda] = ['moneda' => $moneda, 'monto' => 0.0, 'ocs' => 0];
            }
            $por_moneda[$moneda]['monto'] += $oc['total'];
            $por_moneda[$moneda]['ocs']++;
        }

        foreach ($por_moneda as $clave => $moneda) {
            $por_moneda[$clave]['monto'] = round($moneda['monto'], 2);
        }

        // Los items se agrupan por la OC a la que pertenecen.
        $items_por_oc = [];
        foreach ($items as $item) {
            $items_por_oc[$item['id_orden_compra']][] = $item;
        }

        foreach ($ocs as $indice => $oc) {
            $ocs[$indice]['items'] = $items_por_oc[$oc['id_orden_compra']] ?? [];
        }

        return [
            'ocs' => $ocs,
            'items' => $items,
            'resumen' => [
                'total_ocs' => count($ocs),
                'items_pendientes' => $items_pendientes,
                'por_moneda' => array_values($por_moneda),
                'antiguedad_max_dias' => $antiguedad_max,
                'transferencias_en_transito' => (int) $senales['transferencias_en_transito'],
                'recepciones_con_incidencia' => (int) $senales['recepciones_con_incidencia'],
            ],
        ];
    }

    /**
     * Indicador 5: cobros pendientes (cartera por cobrar).
     */
    private static function armar_cobros(Carbon $inicio, Carbon $fin): array
    {
        $items = CobrosPendientesData::get_comprobantes_pendientes(
            $inicio->toDateString(),
            $fin->toDateString()
        );

        $anticipos = CobrosPendientesData::get_anticipos_con_saldo();

        $por_moneda = [];
        $por_cliente = [];

        foreach ($items as $item) {
            $moneda = $item['moneda'];
            if (!isset($por_moneda[$moneda])) {
                $por_moneda[$moneda] = ['moneda' => $moneda, 'saldo' => 0.0, 'comprobantes' => 0];
            }
            $por_moneda[$moneda]['saldo'] += $item['saldo'];
            $por_moneda[$moneda]['comprobantes']++;

            $cliente = $item['cliente'] ?? 'Sin cliente';
            if (!isset($por_cliente[$cliente])) {
                $por_cliente[$cliente] = ['cliente' => $cliente, 'saldo' => 0.0, 'comprobantes' => 0];
            }
            $por_cliente[$cliente]['saldo'] += $item['saldo'];
            $por_cliente[$cliente]['comprobantes']++;
        }

        foreach ($por_moneda as $clave => $moneda) {
            $por_moneda[$clave]['saldo'] = round($moneda['saldo'], 2);
        }

        foreach ($por_cliente as $clave => $cliente) {
            $por_cliente[$clave]['saldo'] = round($cliente['saldo'], 2);
        }

        usort($por_cliente, fn (array $a, array $b) => $b['saldo'] <=> $a['saldo']);

        $saldo_anticipos = 0.0;
        foreach ($anticipos as $anticipo) {
            $saldo_anticipos += $anticipo['saldo_actual'];
        }

        return [
            'items' => $items,
            'anticipos' => $anticipos,
            'resumen' => [
                'total_comprobantes' => count($items),
                'por_moneda' => array_values($por_moneda),
                'por_cliente' => array_values($por_cliente),
                'saldo_anticipos' => round($saldo_anticipos, 2),
            ],
        ];
    }

    /**
     * Indicador 8: pendientes de atencion (embudo logistico).
     */
    private static function armar_pendientes(?int $id_almacen): array
    {
        $etapas = PendientesAtencionData::get_conteo_por_etapa($id_almacen);
        $vencidos = PendientesAtencionData::get_vencidos($id_almacen);

        $total = 0;
        $por_tipo = [];

        foreach ($etapas as $etapa) {
            $total += $etapa['cantidad'];
            $tipo = $etapa['tipo'];

            if (!isset($por_tipo[$tipo])) {
                $por_tipo[$tipo] = ['tipo' => $tipo, 'cantidad' => 0];
            }
            $por_tipo[$tipo]['cantidad'] += $etapa['cantidad'];
        }

        return [
            'etapas' => $etapas,
            'vencidos' => $vencidos,
            'resumen' => [
                'total' => $total,
                'vencidos' => count($vencidos),
                'por_tipo' => array_values($por_tipo),
            ],
        ];
    }

    /**
     * Indicador 9: costo de mantenimiento del periodo y su composicion.
     */
    private static function armar_costo_mantenimiento(Carbon $inicio, Carbon $fin): array
    {
        $mantenimientos = MantenimientoData::get_costo_mantenimiento(
            $inicio->toDateString(),
            $fin->toDateString()
        );

        $costo_mano_obra = 0.0;
        $costo_otros_gastos = 0.0;
        $costo_insumos = 0.0;

        $por_activo = [];

        foreach ($mantenimientos as $mantenimiento) {
            $costo_mano_obra += $mantenimiento['costo_mano_obra'];
            $costo_otros_gastos += $mantenimiento['costo_otros_gastos'];
            $costo_insumos += $mantenimiento['costo_insumos'];

            $clave = $mantenimiento['id_activo_fijo'];
            if (!isset($por_activo[$clave])) {
                $por_activo[$clave] = [
                    'id_activo_fijo' => $clave,
                    'activo_correlativo' => $mantenimiento['activo_correlativo'],
                    'activo_producto' => $mantenimiento['activo_producto'],
                    'cantidad' => 0,
                    'costo_total' => 0.0,
                ];
            }
            $por_activo[$clave]['cantidad']++;
            $por_activo[$clave]['costo_total'] += $mantenimiento['costo_total'];
        }

        foreach ($por_activo as $clave => $activo) {
            $por_activo[$clave]['costo_total'] = round($activo['costo_total'], 2);
        }

        $por_activo = array_values($por_activo);
        usort($por_activo, fn (array $a, array $b) => $b['costo_total'] <=> $a['costo_total']);

        $costo_total = $costo_mano_obra + $costo_otros_gastos + $costo_insumos;
        $cantidad = count($mantenimientos);

        return [
            'mantenimientos' => $mantenimientos,
            'por_activo' => $por_activo,
            'resumen' => [
                'cantidad' => $cantidad,
                'costo_mano_obra' => round($costo_mano_obra, 2),
                'costo_otros_gastos' => round($costo_otros_gastos, 2),
                'costo_insumos' => round($costo_insumos, 2),
                'costo_total' => round($costo_total, 2),
                'costo_promedio' => $cantidad > 0 ? round($costo_total / $cantidad, 2) : 0.0,
            ],
        ];
    }

    /**
     * Total de alertas abiertas en el dashboard.
     */
    private static function total_alertas(
        array $stock_critico,
        array $mantenimiento,
        array $compras,
        array $cobros,
        array $pendientes
    ): int {
        return (int) $stock_critico['resumen']['total_items']
            + (int) $mantenimiento['resumen']['vencidos']
            + (int) $mantenimiento['resumen']['criticos']
            + (int) $compras['resumen']['total_ocs']
            + (int) $cobros['resumen']['total_comprobantes']
            + (int) $pendientes['resumen']['total'];
    }
}
