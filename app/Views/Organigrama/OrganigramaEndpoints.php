<?php

use App\Views\Organigrama\OrganigramaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('organigrama')->controller(OrganigramaController::class)->group(function () {

        Route::get('/areas', 'get_areas');
        Route::post('/areas', 'crear_area');

        Route::get('/cargos', 'get_cargos');
        Route::post('/cargos', 'crear_cargo');
    });
});
