<?php

use App\Modules\ContratosEmpleado\Controllers\ContratosEmpleadoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('contratos-empleado')->controller(ContratosEmpleadoController::class)->group(function () {
        // Listar / ver
        Route::get('/', 'get_contratos');
        Route::get('/{id_contrato}', 'get_contrato_by_id');

        // Historial por empleado
        Route::get('/empleado/{id_empleado}/historial', 'get_historial_por_empleado');

        // Registrar
        Route::post('/', 'crear_contrato');

        // Finalizar anticipadamente
        Route::post('/{id_contrato}/finalizar-anticipado', 'finalizar_anticipado');
    });
});
