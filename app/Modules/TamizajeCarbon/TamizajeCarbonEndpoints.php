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
        Route::put('/{id_zarandeo_carbon}', 'actualizar_tamizaje');
        Route::get('stocks', 'get_stocks');
        Route::put('stocks/{id_stock_carbon}', 'actualizar_stock');
        Route::get('grupos-cargas', 'get_grupos_cargas');

        // Grupos de gastos de produccion: agrupan varios zarandeos o varios
        // blendings de una misma operacion para registrarle los gastos.
        Route::get('grupos-gastos', 'get_grupos_gastos');
        Route::post('grupos-gastos', 'crear_grupo_gastos');

        // Gastos de produccion: cuelgan de un grupo (de zarandeo o de blending).
        Route::get('gastos/grupo/{id_grupo_gastos}', 'get_gastos_grupo');

        // Exportacion de gastos de produccion.
        Route::get('gastos/exportar/rango', 'get_gastos_exportar_rango');
        Route::get('gastos/exportar/grupo/{id_grupo_gastos}', 'get_gastos_exportar_grupo');

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
