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
     * @param array<string, array{inicio: string, fin: string}> $periodos
     *        Periodo de cada indicador de flujo: `balance`, `cobros` y
     *        `costo`. Los indicadores de estado no llevan periodo.
     */
    public static function get_indicadores(array $periodos = []): array
    {
        try {
            $periodo_balance = self::periodo($periodos, 'balance');
            $periodo_cobros = self::periodo($periodos, 'cobros');
            $periodo_costo = self::periodo($periodos, 'costo');
            $periodo_compras = self::periodo($periodos, 'compras');

            // Cada bloque se aísla: si un indicador falla, el resto del
            // dashboard sigue funcionando y el bloque roto lo dice.
            //
            // El alcance por almacen NO se resuelve en el servidor: cada
            // indicador tiene su propio filtro y el usuario puede cambiarlo
            // sin volver a pedir los datos.
            $stock_critico = self::bloque(
                fn () => self::armar_stock_critico(),
                ['items' => [], 'resumen' => ['total_items' => 0, 'criticos' => 0, 'sin_stock' => 0, 'almacenes' => []]]
            );

            $mantenimiento = self::bloque(
                fn () => self::armar_mantenimiento(),
                ['items' => [], 'resumen' => ['vencidos' => 0, 'criticos' => 0, 'proximos' => 0, 'evaluados' => 0]]
            );

            $balance = self::bloque(
                fn () => self::armar_balance($periodo_balance),
                ['monedas' => [], 'origenes' => [], 'ambitos' => []]
            );

            $compras = self::bloque(
                fn () => self::armar_compras($periodo_compras),
                ['ocs' => [], 'items' => [], 'resumen' => ['total_ocs' => 0, 'items_pendientes' => 0, 'por_moneda' => [], 'antiguedad_max_dias' => 0, 'transferencias_en_transito' => 0, 'recepciones_con_incidencia' => 0]]
            );

            $cobros = self::bloque(
                fn () => self::armar_cobros($periodo_cobros),
                ['items' => [], 'anticipos' => [], 'resumen' => ['total_comprobantes' => 0, 'por_moneda' => [], 'por_cliente' => [], 'saldo_anticipos' => 0]]
            );

            $pendientes = self::bloque(
                fn () => self::armar_pendientes(),
                ['etapas' => [], 'vencidos' => [], 'resumen' => ['total' => 0, 'vencidos' => 0, 'por_tipo' => []]]
            );

            $costo_mantenimiento = self::bloque(
                fn () => self::armar_costo_mantenimiento($periodo_costo),
                ['mantenimientos' => [], 'por_activo' => [], 'resumen' => ['cantidad' => 0, 'costo_mano_obra' => 0, 'costo_otros_gastos' => 0, 'costo_insumos' => 0, 'costo_total' => 0, 'costo_promedio' => 0]]
            );

            $payload = [
                'periodos' => [
                    'balance' => self::periodo_publico($periodo_balance),
                    'cobros' => self::periodo_publico($periodo_cobros),
                    'costo' => self::periodo_publico($periodo_costo),
                    'compras' => self::periodo_publico($periodo_compras),
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
     * Normaliza el periodo de un indicador de flujo.
     *
     * Cada indicador de flujo tiene su propio periodo: el balance puede
     * mirar un mes y los cobros otro. El comparativo es el periodo
     * inmediatamente anterior y de la misma duracion.
     *
     * @param array<string, array{inicio: string, fin: string}> $periodos
     * @return array{inicio: Carbon, fin: Carbon, comparativo_inicio: Carbon, comparativo_fin: Carbon}
     */
    private static function periodo(array $periodos, string $clave): array
    {
        $hoy = Carbon::now();

        $inicio = Carbon::parse(
            $periodos[$clave]['inicio'] ?? $hoy->copy()->startOfMonth()->toDateString()
        )->startOfDay();

        $fin = Carbon::parse(
            $periodos[$clave]['fin'] ?? $inicio->copy()->endOfMonth()->toDateString()
        )->startOfDay();

        $dias = $inicio->diffInDays($fin) + 1;

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'comparativo_inicio' => $inicio->copy()->subDays($dias),
            'comparativo_fin' => $inicio->copy()->subDay(),
        ];
    }

    /**
     * Periodo de un indicador de flujo en el formato que consume el front.
     *
     * @param array{inicio: Carbon, fin: Carbon, comparativo_inicio: Carbon, comparativo_fin: Carbon} $periodo
     */
    private static function periodo_publico(array $periodo): array
    {
        return [
            'fecha_inicio' => $periodo['inicio']->toDateString(),
            'fecha_fin' => $periodo['fin']->toDateString(),
            'dias' => $periodo['inicio']->diffInDays($periodo['fin']) + 1,
            'comparativo_inicio' => $periodo['comparativo_inicio']->toDateString(),
            'comparativo_fin' => $periodo['comparativo_fin']->toDateString(),
        ];
    }

    /**
     * Indicador 1: stock critico por almacen.
     */
    private static function armar_stock_critico(): array
    {
        $items = StockCriticoData::get_stock_critico();

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
    private static function armar_mantenimiento(): array
    {
        $evaluados = MantenimientoData::get_alertas_mantenimiento();

        $items = [];
        $vencidos = 0;
        $criticos = 0;
        $proximos = 0;

        foreach ($evaluados as $activo) {
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
                
                'evaluados' => count($evaluados),
            ],
        ];
    }

    /**
     * Indicador 3: balance del periodo (ingresos, egresos y resultado).
     */
    private static function armar_balance(array $periodo): array
    {
        $inicio = $periodo['inicio'];
        $fin = $periodo['fin'];
        $comparativo_inicio = $periodo['comparativo_inicio'];
        $comparativo_fin = $periodo['comparativo_fin'];

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

        $origenes = self::origenes_con_comparativo(
            $resumen_actual['origenes'],
            $resumen_comparativo['origenes']
        );

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
     * Cruza los origenes del periodo actual con los del comparativo.
     *
     * Cada origen lleva su monto anterior y su variacion, para que se pueda
     * leer de un vistazo si el movimiento crecio o cayo, igual que en el
     * modulo de Balances.
     *
     * @param array<int, array<string, mixed>> $actual
     * @param array<int, array<string, mixed>> $comparativo
     */
    private static function origenes_con_comparativo(array $actual, array $comparativo): array
    {
        $anteriores = [];

        foreach ($comparativo as $origen) {
            $clave = self::clave_origen($origen);
            $anteriores[$clave] = (float) $origen['monto'];
        }

        $resultado = [];

        foreach ($actual as $origen) {
            $clave = self::clave_origen($origen);
            $anterior = $anteriores[$clave] ?? 0.0;
            $monto = (float) $origen['monto'];

            $resultado[] = [
                'origen' => $origen['origen'],
                'ambito' => $origen['ambito'],
                'tipo' => $origen['tipo'],
                'moneda' => $origen['moneda'],
                'monto' => round($monto, 2),
                'anterior' => round($anterior, 2),
                'variacion' => $anterior > 0
                    ? round((($monto - $anterior) / abs($anterior)) * 100, 1)
                    : null,
            ];
        }

        return $resultado;
    }

    /**
     * Clave de un origen del balance.
     *
     * @param array<string, mixed> $origen
     */
    private static function clave_origen(array $origen): string
    {
        return implode('|', [
            $origen['origen'],
            $origen['ambito'],
            $origen['tipo'],
            $origen['moneda'],
        ]);
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
    private static function armar_compras(array $periodo): array
    {
        $ocs = ComprasPendientesData::get_ocs_pendientes(
            $periodo['inicio']->toDateString(),
            $periodo['fin']->toDateString()
        );
        $items = ComprasPendientesData::get_items_pendientes(
            $periodo['inicio']->toDateString(),
            $periodo['fin']->toDateString()
        );
        $senales = ComprasPendientesData::get_senales_transito(
            $periodo['inicio']->toDateString(),
            $periodo['fin']->toDateString()
        );

        $por_moneda = [];
        $total_items_pendientes = 0;
        $total_cantidad_pendiente = 0.0;
        $antiguedad_max = 0;

        foreach ($ocs as $oc) {
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

        // Lo que falta por recibir de cada orden: lo pedido menos lo ya
        // recibido. Una orden a medio camino NO esta pendiente de verdad.
        foreach ($ocs as $indice => $oc) {
            $items_oc = $items_por_oc[$oc['id_orden_compra']] ?? [];

            $pendiente = 0.0;
            $items_pendientes = 0;

            foreach ($items_oc as $item) {
                if ($item['cantidad_pendiente_base'] > 0) {
                    $pendiente += $item['cantidad_pendiente_base'];
                    $items_pendientes++;
                }
            }

            $ocs[$indice]['items'] = $items_oc;
            $ocs[$indice]['items_pendientes'] = $items_pendientes;
            $ocs[$indice]['cantidad_pendiente_base'] = round($pendiente, 2);

            $total_items_pendientes += $items_pendientes;
            $total_cantidad_pendiente += $pendiente;
        }

        return [
            'ocs' => $ocs,
            'items' => $items,
            'resumen' => [
                'total_ocs' => count($ocs),
                'items_pendientes' => $total_items_pendientes,
                'cantidad_pendiente_base' => round($total_cantidad_pendiente, 2),
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
    private static function armar_cobros(array $periodo): array
    {
        $items = CobrosPendientesData::get_comprobantes_pendientes(
            $periodo['inicio']->toDateString(),
            $periodo['fin']->toDateString()
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
    private static function armar_pendientes(): array
    {
        $etapas = PendientesAtencionData::get_conteo_por_etapa();
        $vencidos = PendientesAtencionData::get_vencidos();

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
    private static function armar_costo_mantenimiento(array $periodo): array
    {
        $mantenimientos = MantenimientoData::get_costo_mantenimiento(
            $periodo['inicio']->toDateString(),
            $periodo['fin']->toDateString()
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
}
