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
        Route::post('/', 'registrar_uso');
    });
});
