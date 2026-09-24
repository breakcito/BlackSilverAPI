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
        Route::post('/consumir', 'registrar_consumo');
        Route::post('/consumo-directo', 'registrar_consumo_directo');
        Route::patch('/{id}/turno', 'actualizar_turno');
        Route::get('/gastos-extra', 'get_gastos_extra');
        Route::post('/gastos-extra', 'registrar_gasto_extra');
    });
});
