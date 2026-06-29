<?php

namespace App\Modules\LoteMineral\Controller;

use App\Modules\LoteMineral\Service\LoteMineralService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class LoteMineralController extends Controller
{
    /**
     * Listar lotes de mineral
     */
    public function get_lotes(Request $request): JsonResponse
    {
        $mes = $request->query('mes') ? (int) $request->query('mes') : null;
        $anio = $request->query('anio') ? (int) $request->query('anio') : null;

        $result = LoteMineralService::get_lotes($mes, $anio);
        return response()->json($result);
    }

    /**
     * Registrar lote de mineral
     */
    public function registrar_lote(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'id_contratista'   => 'required|integer',
            'id_mina'          => 'required|integer',
            'id_labor'         => 'nullable|integer',
            'descripcion'      => 'nullable|string',
            'fecha_inicio_produccion' => 'nullable|date',
        ], [
            'id_contratista.required' => 'El contratista es requerido',
            'id_mina.required' => 'La mina es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = LoteMineralService::registrar_lote(
            id_contratista: (int) $request->input('id_contratista'),
            id_mina: (int) $request->input('id_mina'),
            id_labor: (int) $request->input('id_labor'),
            id_empleado_registro: (int) $authUser->id_empleado,
            descripcion: $request->input('descripcion') ? (string) $request->input('descripcion') : null,
            fecha_inicio_produccion: $request->input('fecha_inicio_produccion') ? (string) $request->input('fecha_inicio_produccion') : null
        );

        return response()->json($result);
    }
}
