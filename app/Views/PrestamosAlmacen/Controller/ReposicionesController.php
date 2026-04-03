<?php

namespace App\Views\PrestamosAlmacen\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Service\ReposicionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ReposicionesController extends Controller
{
    /**
     * Obtiene el historial de reposiciones de un préstamo.
     */
    public function get_historial(Request $request): JsonResponse
    {
        $id_prestamo_almacen = (int) $request->query('id_prestamo_almacen');
        if (!$id_prestamo_almacen) {
            return response()->json(ApiResponse::error('El id_prestamo_almacen es requerido'), 400);
        }

        $result = ReposicionesService::get_historial($id_prestamo_almacen);
        return response()->json($result);
    }

    /**
     * Registra una nueva reposición.
     */
    public function registrar_reposicion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_prestamo_almacen' => 'required|integer',
            'id_almacen_entrega' => 'required|integer',
            'fecha_hora_reposicion' => 'required|date',
            'observacion' => 'nullable|string',
            'items' => 'required', // Puede ser string JSON o array
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

        // Decodificamos el JSON de los items si vienen como string (común en Multipart)
        $items = $request->input('items');
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        // Validación adicional para los items después de decodificar
        if (!is_array($items) || count($items) === 0) {
            return response()->json(ApiResponse::error('Los items son requeridos y deben ser un array válido'), 400);
        }

        $result = ReposicionesService::registrar_reposicion(
            (int) $request->input('id_prestamo_almacen'),
            (int) $request->input('id_almacen_entrega'),
            (int) $authUser->id_empleado,
            $request->input('fecha_hora_reposicion'),
            $items,
            $request->input('observacion'),
            $request->file('evidencias')
        );

        return response()->json($result);
    }
}
