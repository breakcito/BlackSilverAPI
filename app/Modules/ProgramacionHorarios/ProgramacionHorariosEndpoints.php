<?php

use App\Modules\ProgramacionHorarios\Controllers\ProgramacionHorariosController;
use App\Modules\ProgramacionHorarios\Controllers\TurnoLaboralController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {

    // Turnos Laborales
    Route::prefix('turnos-laborales')->controller(TurnoLaboralController::class)->group(function () {
        Route::get('/', 'get_turnos');
        Route::get('/{id_turno}', 'get_turno_by_id');
        Route::post('/', 'crear_turno');
        Route::put('/{id_turno}', 'actualizar_turno');
        Route::post('/{id_turno}/cambiar-estado', 'cambiar_estado');
    });

    // Programación de Horarios
    Route::prefix('programacion-horario')->controller(ProgramacionHorariosController::class)->group(function () {
        Route::get('/', 'get_programaciones');
        Route::get('/grilla-semanal', 'get_grilla_semanal');
        Route::get('/{id_programacion}', 'get_programacion_by_id');
        Route::post('/asignar', 'asignar_horario');
        Route::post('/{id_programacion}/cambiar-estado', 'cambiar_estado');
    });
});
