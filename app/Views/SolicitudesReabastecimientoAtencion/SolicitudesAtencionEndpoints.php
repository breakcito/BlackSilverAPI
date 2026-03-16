

<?php

use App\Views\SolicitudesReabastecimientoAtencion\Controller\AtencionController;
use App\Views\SolicitudesReabastecimientoAtencion\Controller\EntregaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('solicitudes-atencion')->group(function () {

        Route::controller(AtencionController::class)->group(function () {
            Route::get('/almacenes', 'get_almacenes'); // listar todos los almacenes para elegir uno de ellos
            Route::get('/', 'get_solicitudes'); // obtener las solicitudes hechas por un almacen
            Route::get('/detalles-by-solicitud', 'get_detalles_solicitud');
            Route::put('/save-decision-detalle', 'update_estado_detalle_solicitud');
            Route::get('/trazabilidad', 'get_trazabilidad');
        });

        // Entregas (Despacho, Stock, Lotes)
        Route::prefix('entregas')->controller(EntregaController::class)->group(function () {
            Route::get('/', 'get_historial_entregas'); // listar entregas en base a una solicitud
            Route::post('/', 'crear_entrega'); // registrar una entrega
            Route::get('/lotes', 'get_lotes_disponibles'); // listar lotes disponibles de un almacen
            Route::get('/empleados', 'get_empleados'); // listar empleados para recibir material
        });

        // Prestamos
        // Route::controller(SolicitudesController::class)->group(function () {
        //     Route::post('/save-solicitud-logistica', 'registrar_solicitud');
        //     Route::get('/solicitudes-logistica', 'get_historial_solicitudes');
        // });
    });
});
