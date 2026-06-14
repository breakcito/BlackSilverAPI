<?php

use App\Modules\MantenimientoActivos\Controller\MantenimientoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('mantenimiento-activos')->group(function () {
        Route::controller(MantenimientoController::class)->group(function () {
            Route::get('/', 'get_mantenimientos');
            Route::post('/', 'crear_mantenimiento');
            Route::get('/productos-despachados', 'get_productos_despachados');
        });
    });
});
