<?php

namespace App\Modules\CompraCarbon\Controller;

use App\Modules\CompraCarbon\Service\CompraCarbonPagosService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompraCarbonPagosController
{
    /**
     * Registra un pago al proveedor (contra comprobante o directo).
     */
    public function registrar_pago_proveedor(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        $this->normalizar_json_fields($request);

        $validator = Validator::make($request->all(), [
            'id_comprobante_compra_carbon' => 'nullable|integer|min:1',
            'id_cuenta_bancaria_empresa' => 'required|integer|min:1',
            'id_cuenta_bancaria_proveedor' => 'nullable|integer|min:1',
            'medio_pago' => 'required|string|in:Transferencia,Depósito,Efectivo',
            'numero_operacion' => 'nullable|string|max:64',
            'fecha_hora_pago' => 'required|string',
            'es_para_detraccion' => 'nullable|boolean',
            'monto_pagado' => 'required|numeric|min:0.01',
            'observacion' => 'nullable|string|max:500',
            'ids_cargas' => 'nullable|array',
            'ids_cargas.*' => 'integer|min:1',
            'anticipos' => 'nullable|array',
            'anticipos.*.id_anticipo_proveedor' => 'required_with:anticipos|integer|min:1',
            'anticipos.*.monto_retirado' => 'required_with:anticipos|numeric|min:0.01',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ], [
            'id_cuenta_bancaria_empresa.required' => 'La cuenta bancaria de la empresa es obligatoria',
            'medio_pago.required' => 'El medio de pago es obligatorio',
            'fecha_hora_pago.required' => 'La fecha y hora de pago son obligatorias',
            'monto_pagado.required' => 'El monto pagado es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        if ($request->input('medio_pago') !== 'Efectivo' && empty($request->input('id_cuenta_bancaria_proveedor'))) {
            return response()->json(ApiResponse::error('Para transferencias o depósitos la cuenta del proveedor es obligatoria'), 422);
        }

        /** @var array<int, \Illuminate\Http\UploadedFile> $archivos */
        $archivos = $request->file('evidencias', []);
        $archivos = is_array($archivos) ? $archivos : [];

        return response()->json(
            CompraCarbonPagosService::registrar_pago_proveedor(
                $id_compra_carbon,
                $request->all(),
                $idEmpleado,
                $archivos
            )
        );
    }

    /**
     * Registra un pago al transportista sujeto a su comprobante de flete.
     */
    public function registrar_pago_transporte(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        if ($request->has('es_para_detraccion')) {
            $request->merge(['es_para_detraccion' => $request->boolean('es_para_detraccion')]);
        }

        $validator = Validator::make($request->all(), [
            'id_comprobante_transporte_carbon' => 'required|integer|min:1',
            'id_cuenta_bancaria_empresa' => 'required|integer|min:1',
            'id_cuenta_bancaria_transportista' => 'nullable|integer|min:1',
            'medio_pago' => 'required|string|in:Transferencia,Depósito,Efectivo',
            'numero_operacion' => 'nullable|string|max:64',
            'fecha_hora_pago' => 'required|string',
            'es_para_detraccion' => 'nullable|boolean',
            'monto_pagado' => 'required|numeric|min:0.01',
            'observacion' => 'nullable|string|max:500',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ], [
            'id_comprobante_transporte_carbon.required' => 'El comprobante de flete es obligatorio',
            'id_cuenta_bancaria_empresa.required' => 'La cuenta bancaria de la empresa es obligatoria',
            'monto_pagado.required' => 'El monto pagado es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        /** @var array<int, \Illuminate\Http\UploadedFile> $archivos */
        $archivos = $request->file('evidencias', []);
        $archivos = is_array($archivos) ? $archivos : [];

        return response()->json(
            CompraCarbonPagosService::registrar_pago_transporte(
                $id_compra_carbon,
                $request->all(),
                $idEmpleado,
                $archivos
            )
        );
    }

    private function normalizar_json_fields(Request $request): void
    {
        foreach (['ids_cargas', 'anticipos'] as $field) {
            $raw = $request->input($field);
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $request->merge([$field => $decoded]);
                }
            }
        }
        if ($request->has('es_para_detraccion')) {
            $request->merge(['es_para_detraccion' => $request->boolean('es_para_detraccion')]);
        }
    }
}
