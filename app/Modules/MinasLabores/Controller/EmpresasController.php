<?php

namespace App\Modules\MinasLabores\Controller;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use App\Modules\MinasLabores\Service\EmpresasService;

class EmpresasController extends Controller
{

    public function get_empresas_ejecutoras(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina');
        if (! $id_mina) {
            return response()->json(ApiResponse::error('id_mina es requerido'), 400);
        }

        return response()->json(EmpresasService::get_empresas_ejecutoras((int) $id_mina));
    }

    public function get_empresas_disponibles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'id_concesion' => 'required|integer',
            'id_mina' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $v = $validator->validated();

        return response()->json(EmpresasService::get_empresas_disponibles(
            id_concesion: (int) $v['id_concesion'],
            id_mina: (int) $v['id_mina']
        ));
    }

    public function asignar_empresa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'id_empresa' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $v = $validator->validated();

        return response()->json(EmpresasService::asignar_empresa(
            id_mina: (int) $v['id_mina'],
            id_empresa: (int) $v['id_empresa'],
        ));
    }
}
