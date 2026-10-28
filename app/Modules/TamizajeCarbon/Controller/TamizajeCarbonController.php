<?php

namespace App\Modules\TamizajeCarbon\Controller;

use App\Modules\TamizajeCarbon\Service\GastoProduccionCarbonService;
use App\Modules\TamizajeCarbon\Service\TamizajeCarbonService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TamizajeCarbonController
{
    /**
     * Sube y acumula nuevas evidencias a un zarandeo existente.
     */
    public function subir_evidencias(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_zarandeo_carbon' => 'required|integer|min:1',
            'evidencias' => 'required|array|min:1',
            'evidencias.*' => 'file|max:10240',
        ], [
            'id_zarandeo_carbon.required' => 'El zarandeo es requerido',
            'evidencias.required' => 'Debe adjuntar al menos un archivo',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $idZarandeo = (int) $request->input('id_zarandeo_carbon');
        $archivos = $request->file('evidencias', []);

        try {
            return response()->json(TamizajeCarbonService::subir_evidencias($idZarandeo, $archivos));
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al subir evidencias: ' . $e->getMessage()), 500);
        }
    }

    /**
     * Elimina una evidencia de un zarandeo por su path_relativo.
     */
    public function eliminar_evidencia(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_zarandeo_carbon' => 'required|integer|min:1',
            'path_relativo' => 'required|string',
        ], [
            'id_zarandeo_carbon.required' => 'El zarandeo es requerido',
            'path_relativo.required' => 'La ruta del archivo es requerida',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $idZarandeo = (int) $request->input('id_zarandeo_carbon');
        $pathRelativo = (string) $request->input('path_relativo');

        return response()->json(TamizajeCarbonService::eliminar_evidencia($idZarandeo, $pathRelativo));
    }

    public function get_stocks(Request $request): JsonResponse
    {
        $opts = [
            'id_almacen' => $request->query('id_almacen'),
            'id_tipo_carbon' => $request->query('id_tipo_carbon'),
            'calidad' => $request->query('calidad'),
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

    /**
     * Grupos de cargas pendientes de zarandeo (por almacén, tipo de carbón y calidad).
     */
    public function get_grupos_cargas(Request $request): JsonResponse
    {
        $opts = [
            'id_almacen' => $request->query('id_almacen'),
            'id_tipo_carbon' => $request->query('id_tipo_carbon'),
        ];

        return response()->json(TamizajeCarbonService::get_grupos_cargas($opts));
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
        $idCargasRaw = $request->input('id_cargas');
        if (is_string($idCargasRaw)) {
            $decoded = json_decode($idCargasRaw, true);
            if (is_array($decoded)) {
                $request->merge(['id_cargas' => $decoded]);
            }
        }
        if ($request->has('es_rezarandeo')) {
            $request->merge(['es_rezarandeo' => $request->boolean('es_rezarandeo')]);
        }

        $validator = Validator::make($request->all(), [
            'id_almacen' => 'nullable|integer|min:1',
            'id_tipo_carbon' => 'required|integer|min:1',
            'calidad' => 'required|string|max:32',
            'id_cargas' => 'nullable|array',
            'id_cargas.*' => 'integer|min:1',
            'cantidad_zarandeada' => 'nullable|numeric|min:0',
            'es_rezarandeo' => 'nullable|boolean',
            'fecha_hora_zarandeo' => 'required|string',
            'variantes' => 'required|array|min:1',
            'variantes.*.id_tipo_variante' => 'required|integer|min:1',
            'variantes.*.porcentaje_extraccion' => 'required|numeric|min:0|max:100',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ], [
            'id_tipo_carbon.required' => 'El tipo de carbón a zarandear es requerido',
            'calidad.required' => 'La calidad del carbón es requerida',
            'fecha_hora_zarandeo.required' => 'La fecha y hora del zarandeo son requeridas',
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

    /**
     * Lista los gastos de producción de un zarandeo.
     */
    public function get_gastos(int $id_zarandeo_carbon): JsonResponse
    {
        return response()->json(GastoProduccionCarbonService::get_gastos($id_zarandeo_carbon));
    }

    /**
     * Registra un gasto de producción asociado a un zarandeo.
     */
    public function registrar_gasto(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        $validator = Validator::make($request->all(), [
            'id_zarandeo_carbon' => 'required|integer|min:1',
            'gasto' => 'required|string|max:32',
            'otro_gasto' => 'nullable|string|max:256',
            'id_proveedor' => 'nullable|integer|min:1',
            'descripcion' => 'nullable|string|max:512',
            'fecha' => 'required|date',
            'numero_reporte' => 'nullable|string|max:64',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i',
            'cantidad' => 'nullable|numeric|min:0',
            'precio_unitario' => 'required|numeric|min:0',
        ], [
            'id_zarandeo_carbon.required' => 'El zarandeo es requerido',
            'gasto.required' => 'El tipo de gasto es obligatorio',
            'fecha.required' => 'La fecha del gasto es obligatoria',
            'precio_unitario.required' => 'El precio unitario es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(
            GastoProduccionCarbonService::registrar_gasto($request->all(), $idEmpleado)
        );
    }
}
