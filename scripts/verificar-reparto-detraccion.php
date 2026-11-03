<?php

/**
 * Compara el reparto de detraccion del backend con una replica en JS.
 *
 * Uso: php artisan tinker --execute="require 'scripts/verificar-reparto-detraccion.php';"
 */

use App\Modules\VentaCarbon\Data\VentaCarbonPagosData;

$casos = [
    [[10000.0, 20000.0, 18953.0], 4895],
    [[100.0], 10],
    [[33.33, 33.33, 33.34], 10],
    [[1234.56, 789.44], 202],
    [[5000.0, 5000.0], 1000],
    [[0.0, 100.0], 10],
];

$metodo = new ReflectionMethod(
    VentaCarbonPagosData::class,
    'repartir_detraccion'
);
$metodo->setAccessible(true);

foreach ($casos as [$totales, $monto]) {
    $mapa = [];
    foreach ($totales as $i => $total) {
        $mapa[$i + 1] = $total;
    }

    $reparto = $metodo->invoke(null, $mapa, $monto);

    echo json_encode([
        'totales' => $totales,
        'monto' => $monto,
        'reparto' => array_values($reparto),
        'suma' => array_sum($reparto),
    ]), PHP_EOL;
}
