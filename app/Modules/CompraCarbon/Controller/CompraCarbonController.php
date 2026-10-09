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

    /**
     * Registro de la orden de compra preliminar / cotización.
     */
    public function crear_compra(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa' => 'required|integer|min:1',
            'id_proveedor' => 'required|integer|min:1',
            'id_tipo_carbon_prometido' => 'required|integer|min:1',
            'toneladas_prometidas' => 'required|numeric|min:0.01',
            'aplica_igv' => 'nullable|boolean',
            'porcentaje_igv' => 'nullable|numeric|min:0|max:100',
            'precio_unitario_cotizado' => 'nullable|numeric|min:0',
            'id_tarifa_carbon' => 'nullable|integer|min:1',
        ], [
            'id_empresa.required' => 'La empresa compradora es requerida',
            'id_proveedor.required' => 'El proveedor es requerido',
            'id_tipo_carbon_prometido.required' => 'Debe indicar el tipo de carbón prometido por el proveedor',
            'toneladas_prometidas.required' => 'Debe ingresar la cantidad de toneladas prometidas',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $authUser = $request->attributes->get('auth_user');
        $idEmpleadoRegistro = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleadoRegistro <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado que registra'), 422);
        }

        return response()->json(
            CompraCarbonService::crear_compra($validator->validated(), $idEmpleadoRegistro)
        );
    }

    /**
     * Ingreso de una o varias cargas de carbón asociadas a la orden de compra.
     */
    public function registrar_cargas(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        $cargasRaw = $request->input('cargas');
        $cargas = is_string($cargasRaw) ? json_decode($cargasRaw, true) : $cargasRaw;
        if (!is_array($cargas) || empty($cargas)) {
            return response()->json(ApiResponse::error('Debe ingresar al menos una carga'), 422);
        }

        $validator = Validator::make(['cargas' => $cargas], [
            'cargas.*.id_tipo_carbon' => 'required|integer|min:1',
            'cargas.*.cantidad' => 'required|numeric|min:0.01',
            'cargas.*.precio_unitario' => 'required|numeric|min:0',
            'cargas.*.pagar_flete' => 'nullable|boolean',
            'cargas.*.id_transportista' => 'required_if:cargas.*.pagar_flete,true|nullable|integer|min:1',
            'cargas.*.costo_flete_por_tonelada' => 'required_if:cargas.*.pagar_flete,true|nullable|numeric|min:0',
            'cargas.*.placa' => 'required|string|max:20',
            'cargas.*.codigo_ticket_balanza' => 'required|string|max:50',
            'cargas.*.fecha_hora_ingreso' => 'required|string',
            'cargas.*.tipo_despacho' => 'required|in:Envio,Recojo,envio,recojo',
            'cargas.*.id_almacen_proveedor_recojo' => 'required_if:cargas.*.tipo_despacho,Recojo,recojo|nullable|integer|min:1',
            'cargas.*.id_almacen_empresa_llegada' => 'nullable|integer|min:1',
            'cargas.*.id_almacen_cliente_llegada' => 'nullable|integer|min:1',
            'cargas.*.id_lugar_extraccion' => 'nullable|integer|min:1',
            'cargas.*.porcentaje_ceniza' => 'nullable|numeric|min:0|max:100',
            'cargas.*.porcentaje_humedad' => 'nullable|numeric|min:0|max:100',
        ], [
            'cargas.*.id_tipo_carbon.required' => 'El tipo de carbón es obligatorio en cada carga',
            'cargas.*.cantidad.required' => 'La cantidad de toneladas es obligatoria en cada carga',
            'cargas.*.placa.required' => 'La placa del vehículo es obligatoria',
            'cargas.*.codigo_ticket_balanza.required' => 'El ticket de balanza es obligatorio',
            'cargas.*.fecha_hora_ingreso.required' => 'La fecha y hora de ingreso son obligatorias',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        // Agrupar archivos por carga: evidencias_0, evidencias_1, etc.
        $archivosPorCarga = [];
        foreach ($cargas as $i => $_) {
            $files = $request->file("evidencias_{$i}", []);
            if (!empty($files)) {
                $archivosPorCarga["evidencias_{$i}"] = is_array($files) ? $files : [$files];
            }
        }
        $generalFiles = $request->file('evidencias', []);
        if (!empty($generalFiles)) {
            $archivosPorCarga['evidencias'] = is_array($generalFiles) ? $generalFiles : [$generalFiles];
        }

        return response()->json(
            CompraCarbonService::registrar_cargas($id_compra_carbon, $cargas, $idEmpleado, $archivosPorCarga)
        );
    }

    /**
     * Cierre de la orden de compra.
     */
    public function cerrar_compra(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        return response()->json(
            CompraCarbonService::cerrar_compra($id_compra_carbon, $idEmpleado)
        );
    }

    /**
     * Anulación de la orden de compra.
     */
    public function anular_compra(Request $request, int $id_compra_carbon): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = (int) ($authUser->id_empleado ?? 0);
        if ($idEmpleado <= 0) {
            return response()->json(ApiResponse::error('No se pudo identificar al empleado'), 422);
        }

        return response()->json(
            CompraCarbonService::anular_compra($id_compra_carbon, $idEmpleado)
        );
    }
}
