<?php

use App\Views\PrestamosAlmacenAtencion\PrestamosAtencionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('prestamos-atencion')->controller(PrestamosAtencionController::class)->group(function () {

        // Auxiliares
        Route::get('/almacenes-autorizados', 'get_almacenes_autorizados');
        Route::get('/empleados',             'get_empleados');
        Route::get('/lotes',                 'get_lotes_disponibles');

        // Listado y Detalle (Ojito)
        Route::get('/prestamos',    'get_prestamos');
        Route::get('/ver',          'get_detalle_prestamo');

        // Despacho
        Route::post('/despacho',    'registrar_despacho');
    });
});
