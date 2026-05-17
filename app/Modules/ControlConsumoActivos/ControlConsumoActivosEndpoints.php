<?php

use App\Modules\ControlConsumoActivos\Controller\ControlConsumoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Control de Consumo de Activos Fijos - Rutas
|--------------------------------------------------------------------------
|*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('control-consumo')->controller(ControlConsumoController::class)->group(function () {
        Route::get('/', 'get_reporte');
    });
});
