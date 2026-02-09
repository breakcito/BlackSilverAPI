<?php

namespace App\Modules\Empresa\Controllers;

use App\Modules\Empresa\Services\CargoService;
use Illuminate\Routing\Controller;

/**
 * Controlador para gestión de cargos, áreas y sus asociaciones.
 */
class CargoController extends Controller
{
    public function __construct(
        private CargoService $cargoService
    ) {}
}
