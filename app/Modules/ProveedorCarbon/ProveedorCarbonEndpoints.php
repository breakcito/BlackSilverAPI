<?php

use App\Modules\ProveedorCarbon\Controllers\ProveedorCarbonController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('proveedores')->controller(ProveedorCarbonController::class)->group(function () {
        Route::get('{id_proveedor}/tipos-carbon', 'get_tipos_por_proveedor');
        Route::put('{id_proveedor}/tipos-carbon', 'set_tipos_por_proveedor');
    });
});