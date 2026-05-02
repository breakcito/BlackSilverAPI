<?php

use App\Middlewares\JwtAuthMiddleware;
use App\Modules\OrdenesCompraRecepcionTransferencias\Controller\AuxController;
use App\Modules\OrdenesCompraRecepcionTransferencias\Controller\RecepcionesController;
use App\Modules\OrdenesCompraRecepcionTransferencias\Controller\TransferenciasController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')
    ->prefix('oc-trans-recepciones')
    ->group(function () {
        // -- Auxiliares --
        Route::get('/almacenes', [AuxController::class, 'get_almacenes']);
        Route::get('/lotes', [AuxController::class, 'get_lotes']);

        // -- Transferencias --
        Route::get('/transferencias', [TransferenciasController::class, 'get_transferencias']);
        Route::get('/transferencias/{id}/detalles', [TransferenciasController::class, 'get_detalles']);

        // -- Recepciones --
        Route::get('/recepciones/{id_transferencia}', [RecepcionesController::class, 'get_historial']);
        Route::post('/recepciones', [RecepcionesController::class, 'registrar']);
    });
