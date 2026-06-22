<?php

use App\Modules\MinasLabores\Controller\AuxController;
use App\Modules\MinasLabores\Controller\EmpresasController;
use App\Modules\MinasLabores\Controller\LaboresController;
use App\Modules\MinasLabores\Controller\MinasController;
use App\Modules\MinasLabores\Controller\ResponsablesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->prefix('minas')->group(function () {

    // ─── Minas ────────────────────────────────────────────────────────────────
    Route::prefix('/')->controller(MinasController::class)->group(function () {
        Route::get('/', 'get_minas_resumen');
        Route::post('/', 'crear_mina');
    });

    // ─── Empresas Ejecutoras ────────────────────────────────────────────────────────────────
    Route::prefix('empresas-ejecutoras')->controller(EmpresasController::class)->group(function () {
        Route::get('/', 'get_empresas_ejecutoras');
        Route::post('/', 'asignar_empresa');
        Route::get('/empresas-disponibles', 'get_empresas_disponibles');
    });

    // ─── Responsables ────────────────────────────────────────────────────────────────
    Route::prefix('responsables')->controller(ResponsablesController::class)->group(function () {
        Route::get('/', 'get_historial_responsables');
        Route::post('/asignar-responsable', 'asignar_responsable');
        Route::post('/inactivar-responsable', 'inactivar_responsable');
    });

    // ─── Labores ──────────────────────────────────────────────────────────────
    Route::prefix('labores')->controller(LaboresController::class)->group(function () {
        Route::get('/tipos', 'get_tipos_labor');
        Route::get('/', 'get_labores');
        Route::post('/', 'crear_labor');
        Route::post('/finalizar', 'finalizar_labor');
    });

    // ─── Auxiliares ────────────────────────────────────────────────────────────────
    Route::prefix('/aux')->controller(AuxController::class)->group(function () {
        Route::get('/concesiones', 'get_concesiones');
    });
});
