<?php

use App\Modules\LugarExtraccionCarbon\Controllers\LugarExtraccionCarbonController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('proveedores')->controller(LugarExtraccionCarbonController::class)->group(function () {
        Route::get('{id_proveedor}/lugares-extraccion', 'get_por_proveedor');
        Route::put('{id_proveedor}/lugares-extraccion', 'set_para_proveedor');
        Route::post('{id_proveedor}/lugares-extraccion', 'agregar_a_proveedor');
    });
});