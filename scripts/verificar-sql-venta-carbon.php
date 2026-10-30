<?php

/**
 * Verificacion de sintaxis SQL de las consultas nuevas de VentaCarbon.
 *
 * - `DB::pretend()` captura el SQL exacto que el codigo ejecutaria, sin
 *   tocar la base de datos.
 * - Cada SQL se pasa a MySQL con PREPARE (que parsea pero NO ejecuta).
 *   Un error 1146 (tabla no existe) o 1054 (columna no existe) significa que
 *   la sintaxis es correcta y solo falta aplicar el SQL plano de migracion.
 *
 * Uso: php artisan tinker --execute="require 'scripts/verificar-sql-venta-carbon.php';"
 */

use Illuminate\Support\Facades\DB;

$casos = [];

$casos['liquidaciones'] = function () {
    App\Modules\VentaCarbon\Data\VentaCarbonPagosData::get_liquidaciones_por_venta([1]);
};

$casos['registrar_liquidacion'] = function () {
    App\Modules\VentaCarbon\Data\VentaCarbonPagosData::registrar_liquidacion([
        'id_venta_carbon' => 1,
        'id_empleado_registro' => 1,
        'id_tipo_carbon' => 1,
        'codigo_orden_compra' => 'OC-1',
        'numero_reporte' => 'REP-1',
        'fecha_aprobacion' => '2026-01-01',
        'version' => 'V1',
        'evidencias' => null,
        'cargas' => [
            ['id_carga_venta_carbon' => 1, 'precio_base' => 10, 'descuento' => 0, 'bono' => 0],
        ],
    ]);
};

$casos['actualizar_liquidacion'] = function () {
    App\Modules\VentaCarbon\Data\VentaCarbonPagosData::actualizar_liquidacion([
        'id_liquidacion_venta_carbon' => 1,
        'id_tipo_carbon' => 1,
        'codigo_orden_compra' => 'OC-1',
        'numero_reporte' => 'REP-1',
        'fecha_aprobacion' => '2026-01-01',
        'version' => 'V1',
        'evidencias' => null,
        'cargas' => [],
    ]);
};

$casos['confirmar_liquidacion'] = function () {
    App\Modules\VentaCarbon\Data\VentaCarbonPagosData::confirmar_liquidacion(1, 1);
};

$casos['registrar_comprobante'] = function () {
    App\Modules\VentaCarbon\Data\VentaCarbonPagosData::registrar_comprobante([
        'id_liquidacion_venta_carbon' => 1,
        'id_empleado_registro' => 1,
        'codigo_comprobante' => 'F001-1',
        'fecha_emision' => '2026-01-01',
        'observacion' => null,
        'evidencias' => null,
    ]);
};

$casos['registrar_pago'] = function () {
    App\Modules\VentaCarbon\Data\VentaCarbonPagosData::registrar_pago([
        'id_comprobante_venta_carbon' => 1,
        'id_empleado_registro' => 1,
        'id_cuenta_bancaria_empresa' => 1,
        'medio_pago' => 'Transferencia',
        'numero_operacion' => 'OP-1',
        'fecha_hora_pago' => '2026-01-01 10:00:00',
        'es_para_detraccion' => false,
        'monto_pagado' => 100,
        'observacion' => null,
        'evidencias' => null,
    ]);
};

$casos['recalcular_comprobante'] = function () {
    App\Modules\VentaCarbon\Data\VentaCarbonPagosData::recalcular_comprobante(1);
};

$casos['get_ventas'] = function () {
    App\Modules\VentaCarbon\Data\VentaCarbonData::get_ventas(['filtros' => 'x']);
};

$casos['get_cargas_por_venta'] = function () {
    App\Modules\VentaCarbon\Data\VentaCarbonData::get_cargas_por_venta([1]);
};

$casos['stock_disponible'] = function () {
    App\Modules\VentaCarbon\Data\VentaCarbonData::calcular_stock_disponible(1, 1, 'Baja');
};

$casos['kardex_costo_compra'] = function () {
    App\Modules\CompraCarbon\Data\CompraCarbonData::actualizar_costo_kardex_carga(1, 100.0);
};

$casos['kardex_listado'] = function () {
    App\Modules\KardexCarbon\Data\KardexCarbonData::get_movimientos([]);
};

$conexion = DB::connection();
$pdo = $conexion->getPdo();

foreach ($casos as $nombre => $caso) {
    echo "── {$nombre}\n";

    try {
        $queries = DB::pretend($caso);
    } catch (\Throwable $e) {
        echo "   ERROR PHP: " . $e->getMessage() . "\n";
        continue;
    }

    foreach ($queries as $q) {
        $sql = $q['query'];

        try {
            $stmt = $pdo->prepare($sql);
            echo "   OK   " . substr(preg_replace('/\s+/', ' ', $sql), 0, 150) . "\n";
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $codigo = (string) $e->getCode();
            // 1146 = la tabla aun no existe (falta el SQL plano)
            // 1054 = la columna aun no existe (falta el SQL plano)
            $faltaEsquema = str_contains($msg, '1146')
                || str_contains($msg, '1054')
                || in_array($codigo, ['1146', '1054'], true);

            if ($faltaEsquema) {
                echo "   SQL OK (falta esquema)  " . substr(preg_replace('/\s+/', ' ', $sql), 0, 110) . "\n";
            } else {
                echo "   ✗ SQL INVALIDO ({$codigo}): {$msg}\n";
                echo "      " . substr(preg_replace('/\s+/', ' ', $sql), 0, 300) . "\n";
            }
        }
    }
}
