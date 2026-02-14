<?php

namespace App\Modules\Personal\Controllers;

use App\Modules\Personal\Services\CargoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CargoController extends Controller
{
    public function __construct(private CargoService $cargoService) {}

    public function get_cargos(): JsonResponse
    {
        $result = $this->cargoService->get_cargos();
        return response()->json($result);
    }
}
