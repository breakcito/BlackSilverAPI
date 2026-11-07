<?php

use App\Modules\VentaMineral\Controller\VentaMineralAnalisisLiquidacionController;
use App\Modules\VentaMineral\Controller\VentaMineralCatalogoController;
use App\Modules\VentaMineral\Controller\VentaMineralComprobanteController;
use App\Modules\VentaMineral\Controller\VentaMineralController;
use App\Modules\VentaMineral\Controller\VentaMineralGastoFleteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Venta de Mineral - Rutas
|--------------------------------------------------------------------------
|
| El despacho es el agregado raiz: todo cuelga de el (gastos, fletes,
| comprobantes, analisis y liquidaciones). Los comprobantes de flete,
| molienda, analisis y liquidacion se registran y editan por separado,
| aunque compartan la misma forma de tabla.
|
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('venta-mineral')->group(function () {

        /* ---------------- Despachos ---------------- */
        Route::controller(VentaMineralController::class)->group(function () {
            Route::get('/despachos', 'get_despachos');
            Route::get('/despachos/{id}', 'get_despacho');
            Route::post('/despachos', 'registrar_despacho');
            Route::put('/despachos/{id}', 'actualizar_despacho');
            Route::delete('/despachos/{id}', 'eliminar_despacho');
            Route::get('/lotes-despachables', 'get_lotes_despachables');
        });

        /* ---------------- Gastos y fletes ---------------- */
        Route::controller(VentaMineralGastoFleteController::class)->group(function () {
            Route::post('/despachos/{id}/gastos', 'registrar_gasto');
            Route::put('/gastos/{id}', 'actualizar_gasto');
            Route::delete('/gastos/{id}', 'eliminar_gasto');

            Route::post('/despachos/{id}/fletes', 'registrar_flete');
            Route::put('/fletes/{id}', 'actualizar_flete');
            Route::post('/fletes/{id}/balanza', 'registrar_balanza');
            Route::delete('/fletes/{id}', 'eliminar_flete');
        });

        /* ---------------- Analisis y liquidaciones ---------------- */
        Route::controller(VentaMineralAnalisisLiquidacionController::class)->group(function () {
            Route::post('/despachos/{id}/analisis', 'registrar_analisis');
            Route::put('/analisis/{id}', 'actualizar_analisis');
            Route::delete('/analisis/{id}', 'eliminar_analisis');

            Route::post('/despachos/{id}/liquidaciones', 'registrar_liquidacion');
            Route::put('/liquidaciones/{id}', 'actualizar_liquidacion');
            Route::delete('/liquidaciones/{id}', 'eliminar_liquidacion');
        });

        /* ---------------- Comprobantes de flete ---------------- */
        Route::controller(VentaMineralComprobanteController::class)->group(function () {
            Route::post('/despachos/{id}/comprobantes-flete', 'registrar_comprobante_flete');
            Route::put('/comprobantes-flete/{id}', 'actualizar_comprobante_flete');
            Route::delete('/comprobantes-flete/{id}', 'eliminar_comprobante_flete');
            Route::post('/comprobantes-flete/{id}/pagos', 'registrar_pago_flete');
        });

        /* ---------------- Comprobantes de molienda ---------------- */
        Route::controller(VentaMineralComprobanteController::class)->group(function () {
            Route::post('/despachos/{id}/comprobantes-molienda', 'registrar_comprobante_molienda');
            Route::put('/comprobantes-molienda/{id}', 'actualizar_comprobante_molienda');
            Route::delete('/comprobantes-molienda/{id}', 'eliminar_comprobante_molienda');
            Route::post('/comprobantes-molienda/{id}/pagos', 'registrar_pago_molienda');
        });

        /* ---------------- Comprobantes de analisis ---------------- */
        Route::controller(VentaMineralComprobanteController::class)->group(function () {
            Route::post('/despachos/{id}/comprobantes-analisis', 'registrar_comprobante_analisis');
            Route::put('/comprobantes-analisis/{id}', 'actualizar_comprobante_analisis');
            Route::delete('/comprobantes-analisis/{id}', 'eliminar_comprobante_analisis');
            Route::post('/comprobantes-analisis/{id}/pagos', 'registrar_pago_analisis');
        });

        /* ---------------- Comprobantes de liquidacion ---------------- */
        Route::controller(VentaMineralComprobanteController::class)->group(function () {
            Route::post('/despachos/{id}/comprobantes-liquidacion', 'registrar_comprobante_liquidacion');
            Route::put('/comprobantes-liquidacion/{id}', 'actualizar_comprobante_liquidacion');
            Route::delete('/comprobantes-liquidacion/{id}', 'eliminar_comprobante_liquidacion');
            Route::post('/comprobantes-liquidacion/{id}/pagos', 'registrar_pago_liquidacion');
        });

        /* ---------------- Catalogos ---------------- */
        Route::controller(VentaMineralCatalogoController::class)->group(function () {
            Route::get('/catalogos/molinos', 'get_molinos');
            Route::post('/catalogos/molinos', 'registrar_molino');
            Route::get('/catalogos/transportistas', 'get_transportistas');
            Route::post('/catalogos/transportistas', 'registrar_transportista');
            Route::get('/catalogos/laboratorios', 'get_laboratorios');
            Route::post('/catalogos/laboratorios', 'registrar_laboratorio');
        });
    });
});
