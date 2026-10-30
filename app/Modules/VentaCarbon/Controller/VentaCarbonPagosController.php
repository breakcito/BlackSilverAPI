<?php

namespace App\Modules\VentaCarbon\Controller;

use App\Modules\VentaCarbon\Service\VentaCarbonPagosService;
use App\Shared\Enums\VentaCarbon\MedioPagoVentaCarbon;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Controller de la cadena de facturacion de la venta de carbon:
 * Liquidacion -> Comprobante -> Pago.
 *
 * Solo valida y orquesta: el SQL vive en `VentaCarbonPagosData`.
 */
class VentaCarbonPagosController
{
    /**
     * Registra una liquidacion con sus cargas y sus precios.
     */
    public function registrar_liquidacion(Request $request, int $id_venta_carbon): JsonResponse
    {
        $idEmpleado = $this->id_empleado($request);
        if ($idEmpleado === null) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado que registra'), 422);
        }

        $cargas = $this->cargas_from_request($request);
        if ($cargas === null) {
            return response()->json(ApiResponse::error('El arreglo de cargas no tiene un formato valido'), 422);
        }

        $validator = Validator::make([
            'id_tipo_carbon' => $request->input('id_tipo_carbon'),
            'codigo_orden_compra' => $request->input('codigo_orden_compra'),
            'numero_reporte' => $request->input('numero_reporte'),
            'fecha_aprobacion' => $request->input('fecha_aprobacion'),
            'version' => $request->input('version'),
            'cargas' => $cargas,
        ], [
            'id_tipo_carbon' => 'required|integer|min:1',
            'codigo_orden_compra' => 'nullable|string|max:128',
            'numero_reporte' => 'nullable|string|max:128',
            'fecha_aprobacion' => 'nullable|date',
            'version' => 'nullable|string|max:64',
            'cargas' => 'required|array|min:1',
            'cargas.*.id_carga_venta_carbon' => 'required|integer|min:1',
            'cargas.*.precio_base' => 'required|numeric|min:0.01',
            'cargas.*.descuento' => 'nullable|numeric|min:0',
            'cargas.*.bono' => 'nullable|numeric|min:0',
        ], [
            'id_tipo_carbon.required' => 'Debe indicar el tipo de carbon de la liquidacion',
            'cargas.required' => 'Debe incluir al menos una carga en la liquidacion',
            'cargas.*.id_carga_venta_carbon.required' => 'Cada carga de la liquidacion es obligatoria',
            'cargas.*.precio_base.required' => 'Debe indicar el precio base de cada carga',
            'cargas.*.precio_base.min' => 'El precio base debe ser mayor a 0',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $payload = $validator->validated();
        $payload['cargas'] = $cargas;

        return response()->json(
            VentaCarbonPagosService::registrar_liquidacion(
                $id_venta_carbon,
                $payload,
                $idEmpleado,
                $this->archivos($request)
            )
        );
    }

    /**
     * Actualiza una liquidacion y recalcula los importes.
     */
    public function actualizar_liquidacion(
        Request $request,
        int $id_venta_carbon,
        int $id_liquidacion_venta_carbon
    ): JsonResponse {
        $idEmpleado = $this->id_empleado($request);
        if ($idEmpleado === null) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado que registra'), 422);
        }

        $cargas = $this->cargas_from_request($request);
        if ($cargas === null) {
            return response()->json(ApiResponse::error('El arreglo de cargas no tiene un formato valido'), 422);
        }

        $validator = Validator::make([
            'id_tipo_carbon' => $request->input('id_tipo_carbon'),
            'codigo_orden_compra' => $request->input('codigo_orden_compra'),
            'numero_reporte' => $request->input('numero_reporte'),
            'fecha_aprobacion' => $request->input('fecha_aprobacion'),
            'version' => $request->input('version'),
            'cargas' => $cargas,
        ], [
            'id_tipo_carbon' => 'required|integer|min:1',
            'codigo_orden_compra' => 'nullable|string|max:128',
            'numero_reporte' => 'nullable|string|max:128',
            'fecha_aprobacion' => 'nullable|date',
            'version' => 'nullable|string|max:64',
            'cargas' => 'required|array|min:1',
            'cargas.*.id_carga_venta_carbon' => 'required|integer|min:1',
            'cargas.*.precio_base' => 'required|numeric|min:0.01',
            'cargas.*.descuento' => 'nullable|numeric|min:0',
            'cargas.*.bono' => 'nullable|numeric|min:0',
        ], [
            'id_tipo_carbon.required' => 'Debe indicar el tipo de carbon de la liquidacion',
            'cargas.required' => 'Debe incluir al menos una carga en la liquidacion',
            'cargas.*.precio_base.required' => 'Debe indicar el precio base de cada carga',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $payload = $validator->validated();
        $payload['cargas'] = $cargas;

        return response()->json(
            VentaCarbonPagosService::actualizar_liquidacion(
                $id_venta_carbon,
                $id_liquidacion_venta_carbon,
                $payload,
                $idEmpleado,
                $this->archivos($request)
            )
        );
    }

    /**
     * Confirma la liquidacion habilitando el registro de su comprobante.
     */
    public function confirmar_liquidacion(
        Request $request,
        int $id_venta_carbon,
        int $id_liquidacion_venta_carbon
    ): JsonResponse {
        $idEmpleado = $this->id_empleado($request);
        if ($idEmpleado === null) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado que registra'), 422);
        }

        return response()->json(
            VentaCarbonPagosService::confirmar_liquidacion(
                $id_venta_carbon,
                $id_liquidacion_venta_carbon,
                $idEmpleado
            )
        );
    }

    /**
     * Registra el comprobante de una liquidacion confirmada.
     */
    public function registrar_comprobante(
        Request $request,
        int $id_venta_carbon,
        int $id_liquidacion_venta_carbon
    ): JsonResponse {
        $idEmpleado = $this->id_empleado($request);
        if ($idEmpleado === null) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado que registra'), 422);
        }

        $validator = Validator::make([
            'codigo_comprobante' => $request->input('codigo_comprobante'),
            'fecha_emision' => $request->input('fecha_emision'),
            'observacion' => $request->input('observacion'),
        ], [
            'codigo_comprobante' => 'required|string|max:128',
            'fecha_emision' => 'required|date',
            'observacion' => 'nullable|string|max:512',
        ], [
            'codigo_comprobante.required' => 'El codigo del comprobante es obligatorio',
            'fecha_emision.required' => 'La fecha de emision es obligatoria',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(
            VentaCarbonPagosService::registrar_comprobante(
                $id_venta_carbon,
                $id_liquidacion_venta_carbon,
                $validator->validated(),
                $idEmpleado,
                $this->archivos($request)
            )
        );
    }

    /**
     * Registra un pago del cliente sobre un comprobante.
     */
    public function registrar_pago(
        Request $request,
        int $id_venta_carbon,
        int $id_comprobante_venta_carbon
    ): JsonResponse {
        $idEmpleado = $this->id_empleado($request);
        if ($idEmpleado === null) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado que registra'), 422);
        }

        $validator = Validator::make([
            'id_cuenta_bancaria_empresa' => $request->input('id_cuenta_bancaria_empresa'),
            'medio_pago' => $request->input('medio_pago'),
            'numero_operacion' => $request->input('numero_operacion'),
            'fecha_hora_pago' => $request->input('fecha_hora_pago'),
            'es_para_detraccion' => $request->boolean('es_para_detraccion'),
            'monto_pagado' => $request->input('monto_pagado'),
            'observacion' => $request->input('observacion'),
        ], [
            'id_cuenta_bancaria_empresa' => 'nullable|integer|min:1',
            'medio_pago' => 'required|string|in:' . implode(',', MedioPagoVentaCarbon::valores()),
            'numero_operacion' => 'nullable|string|max:64',
            'fecha_hora_pago' => 'required|string|max:30',
            'es_para_detraccion' => 'nullable|boolean',
            'monto_pagado' => 'required|numeric|min:0.01',
            'observacion' => 'nullable|string|max:512',
        ], [
            'medio_pago.required' => 'Debe indicar el medio de pago',
            'medio_pago.in' => 'El medio de pago debe ser Transferencia, Depósito o Efectivo',
            'fecha_hora_pago.required' => 'La fecha y hora del pago es obligatoria',
            'monto_pagado.required' => 'El monto pagado es obligatorio',
            'monto_pagado.min' => 'El monto pagado debe ser mayor a 0',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(
            VentaCarbonPagosService::registrar_pago(
                $id_venta_carbon,
                $id_comprobante_venta_carbon,
                $validator->validated(),
                $idEmpleado,
                $this->archivos($request)
            )
        );
    }

    /**
     * Obtiene el id del empleado autenticado.
     */
    private function id_empleado(Request $request): ?int
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);

        return $idEmpleado > 0 ? $idEmpleado : null;
    }

    /**
     * Extrae el arreglo de cargas del request (JSON string o array).
     * @return array<int, array<string, mixed>>|null
     */
    private function cargas_from_request(Request $request): ?array
    {
        $raw = $request->input('cargas');

        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return null;
            }
            $raw = $decoded;
        }

        if (!is_array($raw)) {
            return null;
        }

        return array_values($raw);
    }

    /**
     * Archivos de evidencia enviados en el request.
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    private function archivos(Request $request): array
    {
        $archivos = $request->file('evidencias', []);
        if (!is_array($archivos)) {
            $archivos = [$archivos];
        }

        return array_values(array_filter($archivos));
    }
}
