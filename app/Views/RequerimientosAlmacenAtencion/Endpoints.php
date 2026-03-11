

<?php

use App\Views\RequerimientosAlmacenAtencion\Controller\AtencionController;
use App\Views\RequerimientosAlmacenAtencion\Controller\EntregaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Requermientos de Almacen - Rutas
|--------------------------------------------------------------------------
*/


Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('requerimientos-atencion')->group(function () {
        
        // Gestión de Atención (Aprobación/Rechazo)
        Route::controller(AtencionController::class)->group(function () {
            Route::get('/almacenes-autorizados', 'get_almacenes_autorizados');
            Route::get('/requerimientos', 'get_requerimientos');
            Route::get('/detalles-by-requerimiento', 'get_detalles_requerimiento');
            Route::put('/save-decision-detalle', 'update_estado_detalle_requerimiento');
        });

        // Entregas (Despacho, Stock, Lotes)
        Route::controller(EntregaController::class)->group(function () {
            Route::get('/lotes', 'get_lotes_disponibles');
            Route::post('/save-entrega', 'crear_entrega');
            Route::get('/entregas', 'get_historial_entregas');
        });

    });
});
