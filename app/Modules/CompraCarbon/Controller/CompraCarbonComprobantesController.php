<?php

namespace App\Modules\CompraCarbon\Controller;

use App\Modules\CompraCarbon\Service\CompraCarbonPagosService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Comprobantes de Compra de Carbon: el que entrega el proveedor por la compra
 * (solo si aplica IGV) y el que entrega cada transportista por el flete.
 *
 * Todos los endpoints de escritura viajan como multipart/form-data porque
 * adjuntan evidencias. PHP no puebla `$_FILES` en un PUT multipart, asi que
 * la escritura es POST y no PUT.
 */
class CompraCarbonComprobantesController
{
    /**
     * Comprobante del proveedor de una compra, con sus pagos.
     */
    public function get_comprobante_proveedor(int $id_compra_carbon): JsonResponse
    {
        return response()->json(CompraCarbonPagosService::get_comprobante_proveedor($id_compra_carbon));
    }

    /**
     * Registra el comprobante del proveedor.
     * Campos: codigo_comprobante, fecha_emision, observacion, con_detraccion,
     * porcentaje_detraccion, evidencias[].
     */
    public function registrar_comprobante_proveedor(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado de registro'), 422);
        }

        $validator = Validator::make($request->all(), [
            'codigo_comprobante' => 'required|string|max:64',
            'fecha_emision' => 'required|date',
            'observacion' => 'nullable|string|max:500',
            'con_detraccion' => 'nullable|boolean',
            'porcentaje_detraccion' => 'nullable|numeric|min:0|max:30',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ], [
            'codigo_comprobante.required' => 'El codigo del comprobante es obligatorio',
            'codigo_comprobante.max' => 'El codigo del comprobante es demasiado largo',
            'fecha_emision.required' => 'La fecha de emision del comprobante es obligatoria',
            'fecha_emision.date' => 'La fecha de emision no es una fecha valida',
            'porcentaje_detraccion.max' => 'El porcentaje de detraccion no puede superar el 30%',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(CompraCarbonPagosService::registrar_comprobante_proveedor(
            id_compra_carbon: $id_compra_carbon,
            payload: [
                'codigo_comprobante' => $request->input('codigo_comprobante'),
                'fecha_emision' => $request->input('fecha_emision'),
                'observacion' => $request->input('observacion'),
                'con_detraccion' => $request->boolean('con_detraccion'),
                'porcentaje_detraccion' => $request->input('porcentaje_detraccion', 10),
            ],
            id_empleado: $idEmpleado,
            archivos: $this->archivos($request)
        ));
    }

    /**
     * Grupos de cargas con pago de flete, agrupados por transportista.
     * Cada grupo es un comprobante por registrar.
     */
    public function get_grupos_flete(int $id_compra_carbon): JsonResponse
    {
        return response()->json(CompraCarbonPagosService::get_grupos_flete($id_compra_carbon));
    }

    /**
     * Detalle de un comprobante de flete con sus cargas y sus pagos.
     */
    public function get_comprobante_transporte(int $id_compra_carbon, int $id_comprobante): JsonResponse
    {
        return response()->json(CompraCarbonPagosService::get_comprobante_transporte($id_compra_carbon, $id_comprobante));
    }

    /**
     * Registra el comprobante de flete de un transportista.
     * Campos: id_transportista, ids_detalle_carga[], codigo_comprobante,
     * fecha_emision, observacion, con_detraccion, porcentaje_detraccion,
     * evidencias[].
     *
     * El `total` lo calcula el Service sumando el `descuento_flete` de las
     * cargas indicadas: nunca se acepta un monto del cliente.
     */
    public function registrar_comprobante_transporte(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado de registro'), 422);
        }

        $idsCarga = $request->input('ids_detalle_carga');
        if (is_string($idsCarga)) {
            $idsCarga = json_decode($idsCarga, true);
        }

        $validator = Validator::make(
            array_merge($request->all(), ['ids_detalle_carga' => $idsCarga]),
            [
                'id_transportista' => 'required|integer|min:1',
                'ids_detalle_carga' => 'required|array|min:1',
                'ids_detalle_carga.*' => 'integer|min:1',
                'codigo_comprobante' => 'required|string|max:64',
                'fecha_emision' => 'required|date',
                'observacion' => 'nullable|string|max:500',
                'con_detraccion' => 'nullable|boolean',
                'porcentaje_detraccion' => 'nullable|numeric|min:0|max:30',
                'evidencias' => 'nullable|array',
                'evidencias.*' => 'file',
            ],
            [
                'id_transportista.required' => 'Debe indicar el transportista del comprobante',
                'ids_detalle_carga.required' => 'Debe indicar las cargas que componen el comprobante',
                'ids_detalle_carga.*.integer' => 'Los identificadores de carga deben ser numericos',
                'codigo_comprobante.required' => 'El codigo del comprobante es obligatorio',
            'codigo_comprobante.max' => 'El codigo del comprobante es demasiado largo',
                'fecha_emision.date' => 'La fecha de emision no es una fecha valida',
                'porcentaje_detraccion.max' => 'El porcentaje de detraccion no puede superar el 30%',
            ]
        );

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(CompraCarbonPagosService::registrar_comprobante_transporte(
            id_compra_carbon: $id_compra_carbon,
            payload: [
                'id_transportista' => (int) $request->input('id_transportista'),
                'ids_detalle_carga' => array_map('intval', (array) $idsCarga),
                'codigo_comprobante' => $request->input('codigo_comprobante'),
                'fecha_emision' => $request->input('fecha_emision'),
                'observacion' => $request->input('observacion'),
                'con_detraccion' => $request->boolean('con_detraccion'),
                'porcentaje_detraccion' => $request->input('porcentaje_detraccion', 4),
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
