<?php

namespace App\Views\SolicitudesReabastecimiento\Controller;

use App\Views\SolicitudesReabastecimiento\Service\AuxService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;

class AuxController extends Controller
{
    // Obtener la lista de almacenes, productos y unidades de medida
    public function get_catalogos(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $result = AuxService::get_catalogo(
            $authUser->id_empleado,
        );
        return response()->json($result);
    }
}
