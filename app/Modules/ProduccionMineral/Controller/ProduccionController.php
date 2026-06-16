<?php

namespace App\Modules\ProduccionMineral\Controller;

use App\Modules\ProduccionMineral\Service\ProduccionService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ProduccionController extends Controller
{
    /**
     * Iniciar el proceso de producción para un lote mineral.
     */
    public function iniciar_produccion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_lote_mineral' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $id_lote_mineral = (int) $request->input('id_lote_mineral');
        $res = ProduccionService::iniciar_produccion($id_lote_mineral);

        return response()->json($res);
    }

    /**
     * Obtener el resumen de lotes en producción y sus consumos consolidados.
     */
    public function get_resumen(): JsonResponse
    {
        $res = ProduccionService::get_resumen();
        return response()->json($res);
    }

    /**
     * Finalizar el proceso de producción de un lote mineral.
     */
    public function finalizar_produccion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_lote_mineral' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $id_lote_mineral = (int) $request->input('id_lote_mineral');
        $res = ProduccionService::finalizar_produccion($id_lote_mineral);

        return response()->json($res);
    }
}
