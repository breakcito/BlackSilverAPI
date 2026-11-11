<?php

use App\Modules\TamizajeCarbon\Controller\BlendingCarbonController;
use App\Modules\TamizajeCarbon\Controller\TamizajeCarbonController;
use Illuminate\Support\Facades\Route;

/**
 * Modulo de Produccion de Carbon (Zarandeo + Blending).
 */
Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('tamizaje-carbon')->controller(TamizajeCarbonController::class)->group(function () {
        Route::get('/', 'get_tamizajes');
        Route::post('/', 'registrar_tamizaje');
        Route::get('stocks', 'get_stocks');
        Route::put('stocks/{id_stock_carbon}', 'actualizar_stock');
        Route::get('grupos-cargas', 'get_grupos_cargas');

        // Gastos de produccion: del zarandeo o del blending.
        // La ruta estatica va antes de la dinamica.
        Route::get('gastos/blending/{id_blending_carbon}', 'get_gastos_blending');

        // Exportacion de gastos de produccion.
        Route::get('gastos/exportar/rango', 'get_gastos_exportar_rango');
        Route::get('gastos/exportar/zarandeo/{id_zarandeo_carbon}', 'get_gastos_exportar_zarandeo');
        Route::get('gastos/exportar/blending/{id_blending_carbon}', 'get_gastos_exportar_blending');

        Route::get('gastos/{id_zarandeo_carbon}', 'get_gastos_zarandeo');
        Route::post('gastos', 'registrar_gasto');

        // Confirmacion del pago de un gasto.
        Route::post('gastos/{id_gasto}/pagar', 'confirmar_pago_gasto');

        // Evidencias del zarandeo
        Route::post('evidencias', 'subir_evidencias');
        Route::delete('evidencias', 'eliminar_evidencia');
    });

    // Blendings: mezcla de calidades que produce un lote de carbon.
    Route::prefix('tamizaje-carbon')->controller(BlendingCarbonController::class)->group(function () {
        Route::get('blendings', 'get_blendings');
        Route::post('blendings', 'registrar_blending');

        Route::get('blendings/{id_blending_carbon}', 'get_blending');
        Route::put('blendings/{id_blending_carbon}', 'actualizar_blending');
        Route::post('blendings/{id_blending_carbon}/detalles', 'agregar_detalles');

        // Evidencias del blending (ver, agregar y quitar archivos).
        Route::post('blendings/{id_blending_carbon}/evidencias', 'subir_evidencias');
        Route::delete('blendings/{id_blending_carbon}/evidencias', 'eliminar_evidencia');
    });
});
