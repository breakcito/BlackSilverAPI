

<?php

use App\Controllers\EmpresaController; 
use Illuminate\Support\Facades\Route;


Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('empresas')->controller(EmpresaController::class)->group(function () {
        Route::get('/', 'get_empresas');
        Route::post('/', 'crear_empresa');
        Route::get('/by-session', 'get_empresas_by_session');
    });
});
