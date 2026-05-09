
<?php

use App\Modules\LotesProductos\Controller\AuxController;
use App\Modules\LotesProductos\Controller\LotesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Inventario - Rutas
|--------------------------------------------------------------------------
|*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('lotes-productos')->controller(LotesController::class)->group(function () {
        Route::get('/', 'get_resumen_lotes');
        Route::post('/', 'crear_lote');
        Route::post('/ajustar-stock', 'ajustar_stock');
        Route::get('/tickets', 'get_info_to_tickets');
    });
});
