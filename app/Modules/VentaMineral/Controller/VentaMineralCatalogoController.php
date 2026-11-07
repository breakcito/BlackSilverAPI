<?php

namespace App\Modules\VentaMineral\Controller;

use App\Modules\VentaMineral\Service\VentaMineralCatalogoService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class VentaMineralCatalogoController extends Controller
{
    /**
     * Listar los molinos.
     */
    public function get_molinos(): JsonResponse
    {
        return response()->json(VentaMineralCatalogoService::get_molinos());
    }

    /**
     * Registrar un molino.
     */
    public function registrar_molino(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'razon_social' => 'required|string|max:500',
            'ruc'          => 'nullable|string|max:20',
        ], [
            'razon_social.required' => 'La razon social es obligatoria',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = VentaMineralCatalogoService::registrar_molino([
            'razon_social' => (string) $request->input('razon_social'),
            'ruc'          => $request->input('ruc'),
        ]);

        return response()->json($result);
    }

    /**
     * Listar los transportistas de mineral.
     */
    public function get_transportistas(): JsonResponse
    {
        return response()->json(VentaMineralCatalogoService::get_transportistas());
    }

    /**
     * Registrar un transportista de mineral.
     */
    public function registrar_transportista(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'razon_social' => 'required|string|max:500',
            'ruc'          => 'nullable|string|max:20',
        ], [
            'razon_social.required' => 'La razon social es obligatoria',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = VentaMineralCatalogoService::registrar_transportista([
            'razon_social' => (string) $request->input('razon_social'),
            'ruc'          => $request->input('ruc'),
        ]);

        return response()->json($result);
    }

    /**
     * Listar los laboratorios.
     */
    public function get_laboratorios(): JsonResponse
    {
        return response()->json(VentaMineralCatalogoService::get_laboratorios());
    }

    /**
     * Registrar un laboratorio.
     */
    public function registrar_laboratorio(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'razon_social' => 'required|string|max:500',
            'ruc'          => 'nullable|string|max:20',
        ], [
            'razon_social.required' => 'La razon social es obligatoria',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = VentaMineralCatalogoService::registrar_laboratorio([
            'razon_social' => (string) $request->input('razon_social'),
            'ruc'          => $request->input('ruc'),
        ]);

        return response()->json($result);
    }
}
