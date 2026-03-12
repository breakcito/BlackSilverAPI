<?php

namespace App\Views\KardexProductos\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\KardexProductos\Service\KardexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class KardexController extends Controller
{
    public function get_resumen_kardex(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        $mes = $request->query('mes');
        $yearcito = $request->query('yearcito');

        if (!$id_almacen || !$mes || !$yearcito) {
            return response()->json(ApiResponse::error('Los parametros de almacen, mes y año son requeridos'));
        }

        $result = KardexService::get_resumen_kardex((int) $id_almacen, (int) $mes, (int) $yearcito);

        return response()->json($result);
    }

    public function get_almacenes(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = KardexService::get_almacenes((int) $$authUser->id_empleado);

        return response()->json($result);
    }
}
