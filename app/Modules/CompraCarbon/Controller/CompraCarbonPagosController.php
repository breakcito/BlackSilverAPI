<?php

namespace App\Modules\CompraCarbon\Controller;

use App\Modules\CompraCarbon\Service\CompraCarbonPagosService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Pagos de Compra de Carbon: al proveedor (directos o contra su comprobante) y
 * al transportista (siempre contra su comprobante de flete).
 *
 * Los endpoints de escritura viajan como multipart/form-data porque adjuntan
 * evidencias. PHP no puebla `$_FILES` en un PUT multipart.
 */
class CompraCarbonPagosController
{
    /**
     * Historial completo de pagos de una compra: saldos, grupos de flete,
     * comprobantes y los pagos registrados en cada canal.
     */
    public function get_pagos(int $id_compra_carbon): JsonResponse
    {
        return response()->json(CompraCarbonPagosService::get_pagos($id_compra_carbon));
    }

    /**
     * Registra un pago al proveedor.
     *
     * Campos: id_cuenta_bancaria_empresa, id_cuenta_bancaria_proveedor,
     * medio_pago, numero_operacion, fecha_hora_pago, es_para_detraccion,
     * monto_pagado, observacion, evidencias[].
     *
     * Si la compra aplica IGV el pago queda enlazado a su comprobante; si no,
     * `es_para_detraccion` debe venir en false porque el pago es integro.
     */
    public function registrar_pago_proveedor(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado de registro'), 422);
        }

        $validator = Validator::make($request->all(), [
            'id_cuenta_bancaria_empresa' => 'required|integer|min:1',
            'id_cuenta_bancaria_proveedor' => 'required|integer|min:1',
            'medio_pago' => 'required|string|in:Transferencia,Depósito,Efectivo',
            'numero_operacion' => 'nullable|string|max:64',
            'fecha_hora_pago' => 'required|date',
            'es_para_detraccion' => 'nullable|boolean',
            'monto_pagado' => 'required|numeric|min:0.01',
            'observacion' => 'nullable|string|max:500',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ], [
            'id_cuenta_bancaria_empresa.required' => 'Debe indicar la cuenta bancaria de la empresa de donde sale el dinero',
            'id_cuenta_bancaria_proveedor.required' => 'Debe indicar la cuenta bancaria del proveedor que recibe el dinero',
            'medio_pago.in' => 'El medio de pago debe ser Transferencia, Deposito o Efectivo',
            'fecha_hora_pago.required' => 'La fecha y hora del pago son obligatorias',
            'fecha_hora_pago.date' => 'La fecha y hora del pago no son validas',
            'monto_pagado.required' => 'El monto pagado es obligatorio',
            'monto_pagado.min' => 'El monto pagado debe ser mayor a 0',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(CompraCarbonPagosService::registrar_pago_proveedor(
            id_compra_carbon: $id_compra_carbon,
            payload: [
                'id_cuenta_bancaria_empresa' => (int) $request->input('id_cuenta_bancaria_empresa'),
                'id_cuenta_bancaria_proveedor' => (int) $request->input('id_cuenta_bancaria_proveedor'),
                'medio_pago' => $request->input('medio_pago'),
                'numero_operacion' => $request->input('numero_operacion'),
                'fecha_hora_pago' => $request->input('fecha_hora_pago'),
                'es_para_detraccion' => $request->boolean('es_para_detraccion'),
                'monto_pagado' => $request->input('monto_pagado'),
                'observacion' => $request->input('observacion'),
            ],
            id_empleado: $idEmpleado,
            archivos: $this->archivos($request)
        ));
    }

    /**
     * Registra un pago al transportista.
     *
     * Campos: id_comprobante_transporte_carbon, id_cuenta_bancaria_empresa,
     * id_cuenta_bancaria_transportista, medio_pago, numero_operacion,
     * fecha_hora_pago, es_para_detraccion, monto_pagado, observacion,
     * evidencias[].
     */
    public function registrar_pago_transporte(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado de registro'), 422);
        }

        $validator = Validator::make($request->all(), [
            'id_comprobante_transporte_carbon' => 'required|integer|min:1',
            'id_cuenta_bancaria_empresa' => 'required|integer|min:1',
            'id_cuenta_bancaria_transportista' => 'required|integer|min:1',
            'medio_pago' => 'required|string|in:Transferencia,Depósito,Efectivo',
            'numero_operacion' => 'nullable|string|max:64',
            'fecha_hora_pago' => 'required|date',
            'es_para_detraccion' => 'nullable|boolean',
            'monto_pagado' => 'required|numeric|min:0.01',
            'observacion' => 'nullable|string|max:500',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ], [
            'id_comprobante_transporte_carbon.required' => 'Debe indicar el comprobante de flete al que corresponde el pago',
            'id_cuenta_bancaria_empresa.required' => 'Debe indicar la cuenta bancaria de la empresa de donde sale el dinero',
            'id_cuenta_bancaria_transportista.required' => 'Debe indicar la cuenta bancaria del transportista que recibe el dinero',
            'medio_pago.in' => 'El medio de pago debe ser Transferencia, Deposito o Efectivo',
            'fecha_hora_pago.required' => 'La fecha y hora del pago son obligatorias',
            'fecha_hora_pago.date' => 'La fecha y hora del pago no son validas',
            'monto_pagado.required' => 'El monto pagado es obligatorio',
            'monto_pagado.min' => 'El monto pagado debe ser mayor a 0',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(CompraCarbonPagosService::registrar_pago_transporte(
            id_compra_carbon: $id_compra_carbon,
            payload: [
                'id_comprobante_transporte_carbon' => (int) $request->input('id_comprobante_transporte_carbon'),
                'id_cuenta_bancaria_empresa' => (int) $request->input('id_cuenta_bancaria_empresa'),
                'id_cuenta_bancaria_transportista' => (int) $request->input('id_cuenta_bancaria_transportista'),
                'medio_pago' => $request->input('medio_pago'),
                'numero_operacion' => $request->input('numero_operacion'),
                'fecha_hora_pago' => $request->input('fecha_hora_pago'),
                'es_para_detraccion' => $request->boolean('es_para_detraccion'),
                'monto_pagado' => $request->input('monto_pagado'),
                'observacion' => $request->input('observacion'),
            ],
            id_empleado: $idEmpleado,
            archivos: $this->archivos($request)
        ));
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    private function archivos(Request $request): array
    {
        $archivos = $request->file('evidencias', []);
        if ($archivos instanceof \Illuminate\Http\UploadedFile) {
            return [$archivos];
        }

        return is_array($archivos) ? $archivos : [];
    }
}
