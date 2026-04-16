<?php

namespace App\Modules\MinasLabores\Controller;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use App\Modules\MinasLabores\Service\MinasService;

class MinasController extends Controller
{

    public function get_minas_resumen(Request $request): JsonResponse
    {
        // id_concesion es opcional: si se pasa filtra, si no devuelve todas las minas
        $id_concesion = $request->query('id_concesion');

        return response()->json(MinasService::get_minas_resumen(
            $id_concesion ? (int) $id_concesion : null
        ));
    }

    public function crear_mina(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_concesion' => 'required|integer',
            'nombre' => 'required|string|max:128',
            'descripcion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $v = $validator->validated();

        return response()->json(MinasService::crear_mina(
            id_concesion: (int) $v['id_concesion'],
            nombre: (string) $v['nombre'],
            descripcion: isset($v['descripcion']) ? (string) $v['descripcion'] : null,
        ));
    }
}
