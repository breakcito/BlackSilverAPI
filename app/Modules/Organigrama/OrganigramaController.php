<?php

namespace App\Modules\Organigrama;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use App\Services\AreasService;
use App\Services\CargosService;

class OrganigramaController extends Controller
{
    // ÁREAS

    public function get_areas(Request $request): JsonResponse
    {
        $con_cargos = (bool) $request->input('con_cargos', false);
        $result = AreasService::get_areas(con_cargos: $con_cargos);

        return response()->json($result);
    }

    /**
     * Crear área con cargos iniciales opcionales.
     */
    public function crear_area(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:64',
            'cargos' => 'nullable|array',
            'cargos.*.nombre' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = AreasService::crear_area(
            nombre: (string) $v['nombre'],
            cargos: $v['cargos'] ?? null,
        );

        return response()->json($result);
    }

    // CARGOS

    /**
     * Listar cargos. Admite con_area (boolean) o id_area en la ruta.
     */
    public function get_cargos(Request $request, ?int $id_area = null): JsonResponse
    {
        $con_area = $request->has('con_area')
            ? filter_var($request->input('con_area'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        $result = CargosService::get_cargos(
            id_area: $id_area,
            con_area: $con_area,
        );

        return response()->json($result);
    }

    /**
     * Crear cargo con id_area opcional.
     */
    public function crear_cargo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:64',
            'id_area' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = CargosService::crear_cargo(
            nombre: (string) $v['nombre'],
            id_area: isset($v['id_area']) ? (int) $v['id_area'] : null,
        );

        return response()->json($result);
    }

    /**
     * Cambiar área de un cargo (drag & drop).
     */
    public function actualizar_area_cargo(Request $request, int $id_cargo): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_area' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $id_area = $request->input('id_area') !== null
            ? (int) $request->input('id_area')
            : null;

        $result = CargosService::actualizar_area_cargo(
            id_cargo: $id_cargo,
            id_area: $id_area,
        );

        return response()->json($result);
    }

    public function cambiar_estado_cargo(int $id_cargo): JsonResponse
    {
        $result = OrganigramaService::cambiar_estado_cargo(
            id_cargo: $id_cargo
        );

        return response()->json($result);
    }
}
