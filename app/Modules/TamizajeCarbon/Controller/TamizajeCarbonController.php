<?php

namespace App\Modules\TamizajeCarbon\Controller;

use App\Modules\TamizajeCarbon\Service\TamizajeCarbonService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TamizajeCarbonController
{
    public function get_stocks(Request $request): JsonResponse
    {
        $opts = [
            'id_almacen' => $request->query('id_almacen'),
            'id_tipo_carbon' => $request->query('id_tipo_carbon'),
            'filtros' => $request->query('filtros'),
        ];

        return response()->json(TamizajeCarbonService::get_stocks($opts));
    }

    public function actualizar_stock(Request $request, int $id_stock_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        $nombreEmpleado = trim(($authUser->nombre ?? '') . ' ' . ($authUser->apellido ?? ''));

        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        $validator = Validator::make($request->all(), [
            'stock_actual' => 'required|numeric|min:0',
            'motivo' => 'nullable|string|max:255',
        ], [
            'stock_actual.required' => 'El stock actual es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(
            TamizajeCarbonService::actualizar_stock_manual(
                $id_stock_carbon,
                (float) $request->input('stock_actual'),
                $idEmpleado,
                $nombreEmpleado,
                $request->input('motivo') ? (string) $request->input('motivo') : null
            )
        );
    }

    public function get_tamizajes(Request $request): JsonResponse
    {
        $opts = [
            'id_almacen' => $request->query('id_almacen'),
            'mes' => $request->query('mes'),
            'anio' => $request->query('anio'),
            'filtros' => $request->query('filtros'),
        ];

        return response()->json(TamizajeCarbonService::get_tamizajes($opts));
    }

    public function registrar_tamizaje(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        $variantesRaw = $request->input('variantes');
        if (is_string($variantesRaw)) {
            $decoded = json_decode($variantesRaw, true);
            if (is_array($decoded)) {
                $request->merge(['variantes' => $decoded]);
            }
        }
        if ($request->has('es_retamizaje')) {
            $request->merge(['es_retamizaje' => $request->boolean('es_retamizaje')]);
        }

        $validator = Validator::make($request->all(), [
            'id_almacen' => 'nullable|integer|min:1',
            'id_tipo_carbon' => 'required|integer|min:1',
            'id_empleado_supervisor' => 'nullable|integer|min:1',
            'id_carga_compra_carbon' => 'nullable|integer|min:1',
            'cantidad_tamizada' => 'nullable|numeric|min:0',
            'es_retamizaje' => 'nullable|boolean',
            'fecha_hora_tamizaje' => 'required|string',
            'variantes' => 'required|array|min:1',
            'variantes.*.id_tipo_variante' => 'required|integer|min:1',
            'variantes.*.cantidad_extraida' => 'required|numeric|min:0.001',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ], [
            'id_tipo_carbon.required' => 'El tipo de carbón a tamizar es requerido',
            'fecha_hora_tamizaje.required' => 'La fecha y hora del tamizaje son requeridas',
            'variantes.required' => 'Debe registrar al menos una variante extraída',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        /** @var array<int, \Illuminate\Http\UploadedFile> $archivos */
        $archivos = $request->file('evidencias', []);
        $archivos = is_array($archivos) ? $archivos : [];

        return response()->json(
            TamizajeCarbonService::registrar_tamizaje(
                $request->all(),
                $idEmpleado,
                $archivos
            )
        );
    }
}
