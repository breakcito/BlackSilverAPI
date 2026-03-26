<?php

namespace App\Views\PrestamosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacenAtencion\Service\EntregaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class EntregaController extends Controller
{
    /**
     * Registrar despacho físico por préstamo
     */
    public function registrar_despacho(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_prestamo'           => 'required|integer',
            'id_empleado_recibe'    => 'required|integer',
            'fecha_hora_entrega'    => 'nullable|date',
            'observacion'           => 'nullable|string|max:255',
            'evidencias'            => 'nullable|array',
            'evidencias.*'          => 'file',
            'detalles'              => 'required|array|min:1',
            'detalles.*.id_prestamo_detalle' => 'required|integer',
            'detalles.*.id_lote_salida'      => 'required|integer',
            'detalles.*.cantidad_lote'       => 'required|numeric|min:0.01',
            'detalles.*.cantidad_base'       => 'required|numeric|min:0.01',
            'detalles.*.cantidad_solicitud'  => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = EntregaService::registrar_despacho(
            (int) $request->input('id_prestamo'),
            (int) $authUser->id_empleado,
            (int) $request->input('id_empleado_recibe'),
            (string) $request->input('fecha_hora_entrega'),
            (string) $request->input('observacion'),
            $request->file('evidencias'),
            (array) $request->input('detalles')
        );

        return response()->json($result);
    }

    /**
     * Obtener lotes disponibles para varios productos (Batch).
     */
    public function obtener_lotes_batch(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        $ids_productos_str = $request->query('ids_productos');

        if (!$id_almacen || !$ids_productos_str) {
            return response()->json(ApiResponse::error('Faltan parámetros: id_almacen o ids_productos'), 400);
        }

        $ids_productos = explode(',', $ids_productos_str);
        $ids_productos = array_map('intval', $ids_productos);

        $lotes = \App\Views\PrestamosAlmacenAtencion\Data\AuxData::get_lotes_disponibles_batch($ids_productos, (int)$id_almacen);
        
        return response()->json(ApiResponse::success($lotes));
    }
}
