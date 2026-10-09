<?php

namespace App\Modules\CompraCarbon\Controller;

use App\Modules\CompraCarbon\Service\CompraCarbonPagosService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompraCarbonComprobantesController
{
    /**
     * Registra un comprobante entregado por el proveedor (solo si aplica IGV).
     */
    public function registrar_comprobante_proveedor(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        $this->normalizar_json_fields($request);

        $validator = Validator::make($request->all(), [
            'codigo_comprobante' => 'required|string|max:64',
            'fecha_emision' => 'required|date',
            'observacion' => 'nullable|string|max:500',
            'con_detraccion' => 'nullable|boolean',
            'porcentaje_detraccion' => 'nullable|numeric|min:0|max:30',
            'ids_cargas' => 'required|array|min:1',
            'ids_cargas.*' => 'integer|min:1',
            'anticipos' => 'nullable|array',
            'anticipos.*.id_anticipo_proveedor' => 'required_with:anticipos|integer|min:1',
            'anticipos.*.monto_retirado' => 'required_with:anticipos|numeric|min:0.01',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ], [
            'codigo_comprobante.required' => 'El código del comprobante es obligatorio',
            'fecha_emision.required' => 'La fecha de emisión es obligatoria',
            'ids_cargas.required' => 'Debe asociar al menos una carga a este comprobante',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        /** @var array<int, \Illuminate\Http\UploadedFile> $archivos */
        $archivos = $request->file('evidencias', []);
        $archivos = is_array($archivos) ? $archivos : [];

        return response()->json(
            CompraCarbonPagosService::registrar_comprobante_proveedor(
                $id_compra_carbon,
                $request->all(),
                $idEmpleado,
                $archivos
            )
        );
    }

    /**
     * Registra el comprobante de transporte / flete.
     */
    public function registrar_comprobante_transporte(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        $this->normalizar_json_fields($request);

        $validator = Validator::make($request->all(), [
            'id_transportista' => 'required|integer|min:1',
            'codigo_comprobante' => 'required|string|max:64',
            'fecha_emision' => 'required|date',
            'observacion' => 'nullable|string|max:500',
            'con_detraccion' => 'nullable|boolean',
            'porcentaje_detraccion' => 'nullable|numeric|min:0|max:30',
            'ids_cargas' => 'required|array|min:1',
            'ids_cargas.*' => 'integer|min:1',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ], [
            'id_transportista.required' => 'El transportista es obligatorio',
            'codigo_comprobante.required' => 'El número de factura de transporte es obligatorio',
            'fecha_emision.required' => 'La fecha de emisión es obligatoria',
            'ids_cargas.required' => 'Debe asociar las cargas a este comprobante de flete',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        /** @var array<int, \Illuminate\Http\UploadedFile> $archivos */
        $archivos = $request->file('evidencias', []);
        $archivos = is_array($archivos) ? $archivos : [];

        return response()->json(
            CompraCarbonPagosService::registrar_comprobante_transporte(
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
        if ($request->has('con_detraccion')) {
            $request->merge(['con_detraccion' => $request->boolean('con_detraccion')]);
        }
    }
}
