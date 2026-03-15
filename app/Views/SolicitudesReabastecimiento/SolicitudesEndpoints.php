<?php


use App\Views\SolicitudesReabastecimiento\Controller\AuxController;
use App\Views\SolicitudesReabastecimiento\Controller\SolicitudesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoints para la vista de solicitudes de reabastecimiento
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('solicitudes-reabastecimiento')->controller(SolicitudesController::class)->group(function () {
        // Obtener todas la lista de solicitudes hechas por el usuario
        Route::get('/', 'get_solicitudes');
        // Registrar una solicitud y sus detalles
        Route::post('/', 'crear_solicitud');
        // Obtener los detalles de una solicitud
        Route::get('/detalles-solicitud', 'get_detalles_solicitud');

        Route::prefix('catalogos')->controller(AuxController::class)->group(function () {
            // Obtener la lista de almacenes en las que el empleado
            // solicitante es reesponsable
            
            // Obtener la lista de almacenes donde el empleado es responsable
            // productos y unidades de medida
            Route::get('/', 'get_catalogos');
            // Route::get('/almacenes', 'get_almacenes');
            // Route::get('/productos', 'get_productos');
            // Route::get('/unidades-medida', 'get_unidades_medida');
        });
    });

});
