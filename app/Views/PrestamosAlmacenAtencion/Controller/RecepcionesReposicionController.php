<?php

namespace App\Views\PrestamosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacenAtencion\Service\RecepcionesReposicionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class RecepcionesReposicionController extends Controller
{
    /**
     * Registrar una recepción de reposición.
     */
    public function registrar_recepcion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_reposicion' => 'required|integer',
            'fecha_hora_recepcion' => 'required|date',
            'con_incidencia' => 'required',
            'observacion' => 'nullable|string',
            'items' => 'required', // Array o JSON string
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        // Decodificar items si vienen como string
        $items = $request->input('items');
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        if (!is_array($items) || count($items) === 0) {
            return response()->json(ApiResponse::error('Los items son requeridos'), 400);
        }

        $result = RecepcionesReposicionService::registrar_recepcion(
            (int) $request->input('id_reposicion'),
            (int) $authUser->id_empleado,
            filter_var($request->input('con_incidencia'), FILTER_VALIDATE_BOOLEAN),
            $request->input('observacion'),
            $request->input('fecha_hora_recepcion'),
            $items,
            $request->file('evidencias') ?? []
        );

        return response()->json($result);
    }

    /**
     * Obtener historial de recepciones de una reposición.
     */
    public function get_historial(Request $request): JsonResponse
    {
        $id_reposicion = (int) $request->query('id_reposicion');
        if (!$id_reposicion) {
            return response()->json(ApiResponse::error('ID de reposición requerido'), 400);
        }

        $result = RecepcionesReposicionService::get_historial($id_reposicion);
        return response()->json($result);
    }

    /**
     * Obtener los detalles de una reposición para el proceso de recepción.
     */
    public function get_detalles_para_recepcion(Request $request): JsonResponse
    {
        $id_reposicion = (int) $request->query('id_reposicion');
        if (!$id_reposicion) {
            return response()->json(ApiResponse::error('ID de reposición requerido'), 400);
        }

        $result = RecepcionesReposicionService::get_detalles_para_recepcion($id_reposicion);
        return response()->json(ApiResponse::success($result));
    }
}
