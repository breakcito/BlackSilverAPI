<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Controller;

use App\Views\SolicitudesReabastecimientoAtencion\Service\PrestamosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use App\Shared\Responses\ApiResponse;

class PrestamosController extends Controller
{
    public function get_prestamos_por_solicitud(Request $request): JsonResponse
    {
        $id_solicitud = (int) $request->query('id_solicitud');
        if (!$id_solicitud) {
            return response()->json(ApiResponse::error('El id_solicitud es requerido'), 400);
        }

        $result = PrestamosService::get_prestamos_por_solicitud($id_solicitud);
        return response()->json($result);
    }

    public function crear_prestamo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_solicitud_reabastecimiento' => 'required|integer',
            'id_almacen_prestamista' => 'required|integer',
            'fecha_limite_devolucion' => 'nullable|date',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_solicitud_reabastecimiento_detalle' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
            'detalles.*.comentario' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $fecha_limite = $request->input('fecha_limite_devolucion');
        if (empty($fecha_limite)) $fecha_limite = null;

        $result = PrestamosService::crear_prestamo(
            (int) $request->input('id_solicitud_reabastecimiento'),
            (int) $request->input('id_almacen_prestamista'),
            (int) $authUser->id_empleado,
            (array) $request->input('detalles'),
            $fecha_limite,
            (string) $request->input('observacion')
        );

        return response()->json($result);
    }

    public function obtener_por_id(Request $request): JsonResponse
    {
        $id_prestamo = (int) $request->query('id_prestamo');
        if (!$id_prestamo) {
            return response()->json(ApiResponse::error('El id_prestamo es requerido'), 400);
        }
        $result = PrestamosService::get_prestamo_por_id($id_prestamo);
        return response()->json($result);
    }
}
