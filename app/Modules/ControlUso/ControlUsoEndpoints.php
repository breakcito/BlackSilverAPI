<?php

use App\Modules\ControlUso\Controller\ControlUsoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Control de Uso - Rutas
|--------------------------------------------------------------------------
|*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('control-uso')->controller(ControlUsoController::class)->group(function () {
        Route::get('/', 'get_logs');
        Route::get('/ultimo-horometro/{id_activo_fijo}', 'get_ultimo_horometro');
        Route::get('/ultimo-odometro/{id_activo_fijo}', 'get_ultimo_odometro');
        Route::post('/', 'registrar_uso');

        // Tarifas
        Route::get('/tarifas/{id_activo_fijo}', 'get_tarifas');
        Route::post('/tarifas', 'crear_tarifa');

        // Tipos de Material
        Route::get('/materiales', 'get_materiales');
        Route::post('/materiales', 'crear_material');
    });
});
