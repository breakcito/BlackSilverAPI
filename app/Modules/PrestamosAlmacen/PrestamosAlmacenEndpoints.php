<?php

use App\Modules\PrestamosAlmacen\Controller\PrestamosAlmacenController;
use App\Modules\PrestamosAlmacen\Controller\EntregasController;
use App\Modules\PrestamosAlmacen\Controller\ReposicionesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('prestamos-almacen')->group(function () {

        // Listado y Detalle
        Route::controller(PrestamosAlmacenController::class)->group(function () {
            Route::get('/resumen', 'get_prestamos_resumen');
            Route::get('/detalles-prestamo', 'get_detalles_prestamo');
            Route::get('/trazabilidad',     'get_trazabilidad');
        });

        // Entregas
        Route::controller(EntregasController::class)->group(function () {
            Route::get('/historial-entregas', 'get_historial_entregas');
        });

        // Reposiciones
        Route::controller(ReposicionesController::class)->group(function () {
            Route::get('/historial-reposiciones', 'get_historial');
            Route::post('/registrar-reposicion', 'registrar_reposicion');
        });
    });
});
