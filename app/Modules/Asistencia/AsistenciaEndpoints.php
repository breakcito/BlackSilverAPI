<?php

use App\Modules\Asistencia\Controllers\AsistenciaController;
use App\Modules\Asistencia\Controllers\MarcarAsistenciaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo de Asistencia (Recursos Humanos - Control Personal)
|--------------------------------------------------------------------------
*/

// Endpoints administrativos: protegidos con JWT.
Route::middleware('auth.jwt.custom')->prefix('asistencia')->group(function () {
    Route::get('/', [AsistenciaController::class, 'get_asistencias']);
    Route::get('calcular-planilla', [AsistenciaController::class, 'calcular_planilla']);
    Route::post('marcaje-manual', [AsistenciaController::class, 'registrar_marcaje_manual']);
    Route::get('{id_asistencia}', [AsistenciaController::class, 'get_asistencia_by_id'])
        ->whereNumber('id_asistencia');
});

// Endpoints públicos del flujo /marcar-asistencia (sin auth: la seguridad es el qr_token).
Route::prefix('asistencia-public')->group(function () {
    Route::post('resolver-qr', [MarcarAsistenciaController::class, 'resolver_qr']);
    Route::post('confirmar-asistencia', [MarcarAsistenciaController::class, 'confirmar_asistencia']);
    Route::post('cancelar-proceso', [MarcarAsistenciaController::class, 'cancelar_proceso']);
});
