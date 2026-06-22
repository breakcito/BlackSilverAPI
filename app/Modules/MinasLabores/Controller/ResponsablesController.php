<?php

namespace App\Modules\MinasLabores\Controller;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use App\Modules\MinasLabores\Service\ResponsablesService;

class ResponsablesController extends Controller
{
    public function get_historial_responsables(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina');
        if (!$id_mina) {
            return response()->json(ApiResponse::error('id_mina es requerido'), 400);
        }

        return response()->json(ResponsablesService::get_historial_responsables((int) $id_mina));
    }

    public function asignar_responsable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'id_empleado' => 'required|integer',
            'fecha_inicio' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $v = $validator->validated();

        return response()->json(ResponsablesService::asignar_responsable(
            id_mina: (int) $v['id_mina'],
            id_empleado: (int) $v['id_empleado'],
            fecha_inicio: (string) $v['fecha_inicio'],
        ));
    }

    public function inactivar_responsable(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_responsable_mina' => 'required|integer',
            'fecha_fin' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $v = $validator->validated();

        return response()->json(ResponsablesService::inactivar_responsable(
            id_responsable_mina: (int) $v['id_responsable_mina'],
            fecha_fin: (string) $v['fecha_fin'],
        ));
    }
}
