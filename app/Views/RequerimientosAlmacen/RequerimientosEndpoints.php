
<?php

use App\Views\RequerimientosAlmacen\RequerimientosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Requermientos de Almacen - Rutas
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    // Requerimientos / Solicitud a almacen
    Route::prefix('requerimientos-almacen')->controller(RequerimientosController::class)->group(function () {
        Route::get('/', 'get_requerimientos'); // listar requerimientos en base al empleado logueado y periodo (mes y año)
        Route::post('/', 'crear_requerimiento'); // registrar un requerimiento, enviando datos del requerimeitno, las labores involucradas y su detalle
        Route::get('/detalle', 'get_detalle_by_requerimiento'); // obtener los detalles de un requerimiento
        Route::get('/detalle-trazabilidad', 'get_trazabilidad_by_detalle'); // obtener la trazabilidad del detalle de un requerimiento
        //
        Route::get('/data-to-registro', 'get_data_to_registro'); // obtener minas, productos y unidades de medida
        // Route::get('/minas', 'get_minas'); // obtener las minas donde el empleado logueado es responsables
        // Route::get('/productos', 'get_productos'); // obteenr los productos para registrar un requerimiento
        // Route::get('/unidades', 'get_unidades_medida'); // obtener las unidades de medida para registrar un requerimiento
        Route::get('/data-by-mina', 'get_data_by_mina'); // obtener labores y almacenes
        // Route::get('/almacenes', 'get_almacenes_by_mina'); // en base a la mina elegida, obtener los almacenes que abastecen esa mina
        // Route::get('/labores', 'get_labores_by_mina'); // en base a la mina elegida, obtener las labores/frentes de esa mina
    });
});
