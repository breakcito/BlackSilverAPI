


<?php

use Illuminate\Support\Facades\Route;


Route::middleware('auth.jwt.custom')->group(function () {

    // Concesiones
    Route::prefix('concesiones')->controller(ConcesionController::class)->group(function () {
        Route::get('/', 'get_concesiones'); 
        Route::get('/tipos-mineral', 'get_tipos_mineral');
        Route::get('/by-empresa', 'get_concesiones_by_empresa');
        Route::get('/by-session', 'get_concesiones_by_session');
        Route::post('/', 'crear_concesion'); 
        Route::put('/', 'update_concesion');
        Route::delete('/', 'delete_concesion');
        Route::get('/asignaciones', 'get_empresas_historial'); 
        Route::post('/asignar', 'asignar_empresa');
        Route::delete('/desasignar', 'desasignar_empresa');
    });




});
