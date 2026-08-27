<?php

namespace App\Modules\CompraCarbon\Controller;

use App\Modules\CompraCarbon\Service\CompraCarbonService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompraCarbonController
{
    public function get_compras(Request $request): JsonResponse
    {
        $opts = [
            'filtros' => $request->query('filtros'),
            'id_empresa' => $request->query('id_empresa'),
            'id_proveedor' => $request->query('id_proveedor'),
            'mes' => $request->query('mes'),
            'anio' => $request->query('anio'),
        ];

        return response()->json(CompraCarbonService::get_compras($opts));
    }

    public function get_compra_con_detalles(int $id_compra_carbon): JsonResponse
    {
        return response()->json(
            CompraCarbonService::get_compra_con_detalles($id_compra_carbon)
        );
    }

    public function aprobar_compra(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $id_empleado_aprueba = (int) ($authUser->id_empleado ?? 0);
        if ($id_empleado_aprueba <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado aprobador'), 422);
        }

        return response()->json(
            CompraCarbonService::aprobar_compra($id_compra_carbon, $id_empleado_aprueba)
        );
    }

    public function set_evidencias(Request $request, int $id_compra_carbon): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'evidencias' => 'present|array',
            'evidencias.*.url' => 'required|string',
            'evidencias.*.path_relativo' => 'required|string',
            'evidencias.*.nombre_original' => 'nullable|string',
            'evidencias.*.extension' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        /** @var array<int, array<string, mixed>> $evidencias */
        $evidencias = $request->input('evidencias', []);

        return response()->json(
            CompraCarbonService::set_evidencias($id_compra_carbon, $evidencias)
        );
    }

    public function anular_compra(int $id_compra_carbon): JsonResponse
    {
        return response()->json(
            CompraCarbonService::anular_compra($id_compra_carbon)
        );
    }

    public function crear_compra(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa' => 'required|integer|min:1',
            'id_proveedor' => 'required|integer|min:1',
            'id_almacen' => 'required|integer|min:1',
            'aplica_igv' => 'required|boolean',
            // Si aplica_igv es true debe traer porcentaje (por defecto 18).
            'porcentaje_igv' => 'required_if:aplica_igv,true|nullable|numeric|min:0|max:100',
            'fecha_hora_ingreso' => 'required|date_format:Y-m-d H:i:s',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_tipo_carbon' => 'required|integer|min:1',
            'detalles.*.id_transportista' => 'nullable|integer|min:1',
            'detalles.*.id_lugar_extraccion' => 'nullable|integer|min:1',
            'detalles.*.id_tarifa_carbon' => 'nullable|integer|min:1',
            'detalles.*.placa' => 'nullable|string|max:64',
            'detalles.*.guia_remitente' => 'nullable|string|max:64',
            'detalles.*.guia_transportista' => 'nullable|string|max:64',
            'detalles.*.pagar_flete' => 'required|boolean',
            'detalles.*.codigo_ticket_balanza' => 'nullable|string|max:64',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.porcentaje_ceniza' => 'nullable|numeric|min:0|max:100',
            'detalles.*.porcentaje_humedad' => 'nullable|numeric|min:0|max:100',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.costo_flete_por_tonelada' => 'nullable|numeric|min:0',
            'detalles.*.evidencias' => 'nullable|array',
            'detalles.*.evidencias.*.url' => 'required_with:detalles.*.evidencias|string',
            'detalles.*.evidencias.*.path_relativo' => 'required_with:detalles.*.evidencias|string',
        ], [
            'id_empresa.required' => 'Empresa requerida',
            'id_proveedor.required' => 'Proveedor requerido',
            'id_almacen.required' => 'Almacen requerido',
            'aplica_igv.required' => 'Indica si aplica IGV',
            'porcentaje_igv.required_if' => 'Porcentaje de IGV requerido cuando aplica IGV',
            'fecha_hora_ingreso.required' => 'Fecha y hora de ingreso requeridas',
            'fecha_hora_ingreso.date_format' => 'Formato esperado: Y-m-d H:i:s',
            'detalles.required' => 'La compra debe tener al menos un item',
            'detalles.*.pagar_flete.required' => 'Indica si se paga flete',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $authUser = $request->attributes->get('auth_user');
        $id_empleado_registro = (int) ($authUser->id_empleado ?? 0);
        if ($id_empleado_registro <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado de registro'), 422);
        }

        return response()->json(
            CompraCarbonService::crear_compra($validator->validated(), $id_empleado_registro)
        );
    }
}
