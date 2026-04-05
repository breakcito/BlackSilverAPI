<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Controller;

use App\Views\SolicitudesReabastecimientoAtencion\Service\PrestamosService;
use App\Views\SolicitudesReabastecimientoAtencion\Service\AuxService;
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

    public function get_almacenes_con_stock(Request $request): JsonResponse
    {
        $id_almacen_excluido = (int) $request->query('id_almacen_excluido');
        $ids_productos = (array) $request->query('ids_productos', []);

        if (empty($ids_productos)) {
            return response()->json(ApiResponse::error('No se han especificado productos'), 400);
        }

        $result = AuxService::get_almacenes_con_stock($id_almacen_excluido, $ids_productos);
        return response()->json($result);
    }

    public function get_stock_total_almacen_por_productos(Request $request): JsonResponse
    {
        $id_almacen = (int) $request->query('id_almacen');
        $ids_productos = (array) $request->query('ids_productos', []);

        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        if (empty($ids_productos)) {
            return response()->json(ApiResponse::error('No se han especificado productos'), 400);
        }

        $result = AuxService::get_stock_total_almacen_por_productos($id_almacen, $ids_productos);
        return response()->json($result);
    }

    public function get_lotes_disponibles_por_almacen_y_producto(Request $request): JsonResponse
    {
        $id_almacen = (int) $request->query('id_almacen');
        $id_producto = (int) $request->query('id_producto');

        if (!$id_almacen || !$id_producto) {
            return response()->json(ApiResponse::error('id_almacen e id_producto son requeridos'), 400);
        }

        $result = AuxService::get_lotes_disponibles($id_almacen, [$id_producto]);
        return response()->json($result);
    }
}
