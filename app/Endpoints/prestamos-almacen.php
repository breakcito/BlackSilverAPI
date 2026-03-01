<?php

use App\Controllers\PrestamoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Prestamos de Almacen - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {

    // Rutas para el Almacén que Solicita
    Route::get('/prestamos', [PrestamoController::class, 'get_prestamos']);
    Route::post('/prestamos', [PrestamoController::class, 'crear_prestamo']);

    // Búsqueda de stock en otros almacenes (para el carrito)
    Route::post('/prestamos/buscar-stock', [PrestamoController::class, 'buscar_stock_global']);

    // Vistas especializadas
    Route::post('/prestamos/obtener-por-id', [PrestamoController::class, 'obtener_por_id']);
    Route::post('/prestamos/detalle/trazabilidad', [PrestamoController::class, 'obtener_trazabilidad_detalle']);

    // --- Atencion de Prestamos (Se implementará en la siguiente fase) ---
    // Route::post('/prestamos/atencion/obtener-pendientes', [AtencionPrestamoController::class, '...']);
});
