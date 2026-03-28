<?php

use App\Views\PrestamosAlmacen\Controller\PrestamosAlmacenController;
use App\Views\PrestamosAlmacen\Controller\AuxController;
use App\Views\PrestamosAlmacen\Controller\EntregasController;
use App\Views\PrestamosAlmacen\Controller\ReposicionesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('prestamos-almacen')->group(function () {

        // Auxiliares
        Route::controller(AuxController::class)->group(function () {
            Route::get('/almacenes-secundarios', 'get_almacenes_secundarios');
            Route::get('/almacenes', 'get_almacenes');
            Route::get('/lotes',      'get_lotes_disponibles');
        });

        // Listado y Detalle (Logística)
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

