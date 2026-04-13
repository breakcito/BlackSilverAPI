<?php

use App\Modules\MinasLabores\MinasLaboresController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    // ─── Minas ────────────────────────────────────────────────────────────────
    Route::prefix('minas')->controller(MinasLaboresController::class)->group(function () {
        Route::get('/concesiones', 'get_concesiones');
        Route::get('/', 'get_minas_resumen');
        Route::post('/', 'crear_mina');

        // ─── Empresas Ejecutoras ────────────────────────────────────────────────────────────────
        Route::prefix('empresas-ejecutoras')->controller(MinasLaboresController::class)->group(function () {
            Route::get('/', 'get_empresas_ejecutoras');
            Route::post('/', 'asignar_empresa');
            Route::get('/empresas-disponibles', 'get_empresas_disponibles');
        });

        // ─── Responsables ────────────────────────────────────────────────────────────────
        Route::prefix('responsables')->controller(MinasLaboresController::class)->group(function () {
            Route::get('/', 'get_historial_responsables');
            Route::get('/empleados-disponibles', 'get_empleados_disponibles');
            Route::post('/asignar-responsable', 'asignar_responsable');
        });

        // ─── Labores ──────────────────────────────────────────────────────────────
        Route::prefix('labores')->controller(MinasLaboresController::class)->group(function () {
            Route::get('/tipos', 'get_tipos_labor');
            Route::get('/', 'get_labores');
            Route::post('/', 'crear_labor');
            Route::post('/finalizar', 'finalizar_labor');
        });
    });
});
