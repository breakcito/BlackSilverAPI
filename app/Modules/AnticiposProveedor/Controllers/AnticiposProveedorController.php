<?php

namespace App\Modules\AnticiposProveedor\Controllers;

use App\Modules\AnticiposProveedor\Services\AnticiposProveedorService;
use App\Shared\Enums\AnticipoProveedor\MedioPago;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class AnticiposProveedorController extends Controller
{
    public function listar_por_proveedor(int $id_proveedor): JsonResponse
    {
        return response()->json(
            AnticiposProveedorService::get_por_proveedor($id_proveedor)
        );
    }

    /**
     * Body esperado:
     * {
     *   id_empresa: int (default 1 = Cupper en este proyecto),
     *   id_cuenta_bancaria_empresa?: int,
     *   medio_pago?: 'Transferencia' | 'Depósito' | 'Efectivo',
     *   fecha_hora_pago?: 'YYYY-MM-DD HH:MM:SS',
     *   numero_operacion?: string(64),
     *   saldo: float > 0  // se guarda en saldo_inicial y saldo_actual,
     *   evidencias?: array<{url,path_relativo,nombre_original?,extension?}>  // IArchivo[]
     * }
     *
     * `id_empleado_registro` se toma del JWT (inyectado por el middleware
     * `auth.jwt.custom` en `$request->attributes->get('auth_user')`).
     */
    public function registrar(Request $request, int $id_proveedor): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa' => 'required|integer|min:1',
            'id_cuenta_bancaria_empresa' => 'nullable|integer|min:1',
            'medio_pago' => ['nullable', new Enum(MedioPago::class)],
            'fecha_hora_pago' => 'nullable|date_format:Y-m-d H:i:s',
            'numero_operacion' => 'nullable|string|max:64',
            'saldo' => 'required|numeric|min:0.01',
            'evidencias' => 'nullable|array',
            'evidencias.*.url' => 'required_with:evidencias|string',
            'evidencias.*.path_relativo' => 'required_with:evidencias|string',
        ], [
            'id_empresa.required' => 'La empresa es obligatoria',
            'saldo.required' => 'El saldo es obligatorio',
            'saldo.min' => 'El saldo debe ser mayor a 0',
            'fecha_hora_pago.date_format' => 'Formato de fecha y hora invalido (YYYY-MM-DD HH:MM:SS)',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        // Identidad del usuario autenticado (inyectada por JwtAuthMiddleware).
        $authUser = $request->attributes->get('auth_user');
        $idEmpleadoRegistro = is_object($authUser) && isset($authUser->id_empleado)
            ? (int) $authUser->id_empleado
            : null;

        if ($idEmpleadoRegistro === null || $idEmpleadoRegistro <= 0) {
            return response()->json(
                ApiResponse::error('No se pudo identificar al empleado que registra'),
                401
            );
        }

        $medioPagoRaw = $request->input('medio_pago');
        $medioPago = $medioPagoRaw !== null && $medioPagoRaw !== ''
            ? MedioPago::from($medioPagoRaw)
            : null;

        // evidencias puede venir como array asociativo de IArchivo (ya
        // subidos por el FE via POST /archivos/upload) o como null/array
        // vacio.
        $evidenciasRaw = $request->input('evidencias');
        $evidencias = is_array($evidenciasRaw) && count($evidenciasRaw) > 0
            ? array_values($evidenciasRaw)
            : null;

        $result = AnticiposProveedorService::registrar(
            id_proveedor: $id_proveedor,
            id_empresa: (int) $request->input('id_empresa'),
            id_empleado_registro: $idEmpleadoRegistro,
            id_cuenta_bancaria_empresa: $request->input('id_cuenta_bancaria_empresa') !== null
                ? (int) $request->input('id_cuenta_bancaria_empresa')
                : null,
            medio_pago: $medioPago,
            fecha_hora_pago: $request->input('fecha_hora_pago') ?: null,
            numero_operacion: $request->input('numero_operacion') ?: null,
            saldo: (float) $request->input('saldo'),
            evidencias: $evidencias,
        );

        return response()->json($result);
    }

    /**
     * Anular un anticipo. El id del empleado que anula sale del JWT,
     * igual que el de registro.
     */
    public function anular(Request $request, int $id_proveedor, int $id_anticipo): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleadoAnulacion = is_object($authUser) && isset($authUser->id_empleado)
            ? (int) $authUser->id_empleado
            : null;

        if ($idEmpleadoAnulacion === null || $idEmpleadoAnulacion <= 0) {
            return response()->json(
                ApiResponse::error('No se pudo identificar al empleado que anula'),
                401
            );
        }

        return response()->json(
            AnticiposProveedorService::anular($id_anticipo, $idEmpleadoAnulacion)
        );
    }
}
