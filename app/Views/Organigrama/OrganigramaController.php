<?php

namespace App\Views\Organigrama;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class OrganigramaController extends Controller
{
    // ÁREAS

    public function get_areas(Request $request): JsonResponse
    {
        $result = OrganigramaService::get_areas();

        return response()->json($result);
    }

    public function crear_area(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = OrganigramaService::crear_area($request->nombre);

        return response()->json($result);
    }

    // CARGOS

    public function get_cargos(Request $request, ?int $id_area = null): JsonResponse
    {
        $id_area_final = $id_area ?? $request->input('id_area');

        if (! $id_area_final) {
            return response()->json(ApiResponse::error('El id_area es requerido'));
        }

        $result = OrganigramaService::get_cargos((int) $id_area_final);

        return response()->json($result);
    }

    public function crear_cargo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:64',
            'id_area' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = OrganigramaService::crear_cargo($request->nombre, $request->id_area);

        return response()->json($result);
    }
}
