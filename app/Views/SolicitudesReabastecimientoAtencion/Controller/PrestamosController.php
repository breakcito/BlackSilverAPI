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
            'fecha_limite_devolucion' => 'required|date',
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

        $result = PrestamosService::crear_prestamo(
            (int) $request->input('id_solicitud_reabastecimiento'),
            (int) $request->input('id_almacen_prestamista'),
            (int) $authUser->id_empleado,
            (string) $request->input('fecha_limite_devolucion'),
            (array) $request->input('detalles')
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

    // Métodos auxiliares
    public function get_almacenes_con_stock(Request $request): JsonResponse
    {
        $ids_productos = (array) $request->query('ids_productos');
        $id_almacen_excluido = (int) $request->query('id_almacen_excluido');

        if (empty($ids_productos)) {
            return response()->json(ApiResponse::error('ids_productos es requerido'), 400);
        }

        $result = PrestamosService::get_almacenes_con_stock_multiple_productos($ids_productos, $id_almacen_excluido);
        return response()->json($result);
    }

    public function get_lotes_disponibles_por_almacen_y_producto(Request $request): JsonResponse
    {
        $id_producto = (int) $request->query('id_producto');
        $id_almacen = (int) $request->query('id_almacen');

        if (!$id_producto || !$id_almacen) {
            return response()->json(ApiResponse::error('El id_producto e id_almacen son requeridos'), 400);
        }

        $result = PrestamosService::get_lotes_disponibles_por_almacen_y_producto($id_producto, $id_almacen);
        return response()->json($result);
    }
}
