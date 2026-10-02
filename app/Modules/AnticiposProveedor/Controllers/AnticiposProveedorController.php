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
     * Registra un anticipo.
     *
     * El request es `multipart/form-data`: los adjuntos llegan en el campo
     * `evidencias[]` como `UploadedFile` y los guarda el Service via
     * `ArchivoHelper::guardarArchivos()` (mismo patron que
     * Mantenimientos, OC y Prestamos). El backend NO acepta URLs ni paths
     * de archivos: los genera el al persistir, asi nadie puede inventarse
     * una evidencia que nunca se subio.
     *
     * Campos escalares:
     * {
     *   id_empresa: int (default 1 = Cupper en este proyecto),
     *   id_cuenta_bancaria_empresa?: int,   // cuenta ORIGEN (de la empresa)
     *   id_cuenta_bancaria_proveedor?: int, // cuenta DESTINO (del proveedor)
     *   medio_pago?: 'Transferencia' | 'Depósito' | 'Efectivo',
     *   fecha_hora_pago?: 'YYYY-MM-DD HH:MM:SS',
     *   numero_operacion?: string(64),
     *   codigo_comprobante?: string(64),  // factura / comprobante que respalda el anticipo
     *   observacion?: string(500),
     *   pago_a_terceros?: bool,          // el dinero no fue a cuenta del proveedor
     *   saldo: float > 0  // se guarda en saldo_inicial y saldo_actual,
     *   evidencias[]: UploadedFile        // opcional
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
            'id_cuenta_bancaria_proveedor' => 'nullable|integer|min:1',
            'medio_pago' => ['nullable', new Enum(MedioPago::class)],
            'fecha_hora_pago' => 'nullable|date_format:Y-m-d H:i:s',
            'numero_operacion' => 'nullable|string|max:64',
            'codigo_comprobante' => 'nullable|string|max:64',
            'observacion' => 'nullable|string|max:500',
            'pago_a_terceros' => 'nullable|boolean',
            'saldo' => 'required|numeric|min:0.01',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ], [
            'id_empresa.required' => 'La empresa es obligatoria',
            'saldo.required' => 'El saldo es obligatorio',
            'saldo.min' => 'El saldo debe ser mayor a 0',
            'fecha_hora_pago.date_format' => 'Formato de fecha y hora invalido (YYYY-MM-DD HH:MM:SS)',
            'codigo_comprobante.max' => 'El comprobante no puede superar 64 caracteres',
            'observacion.max' => 'La observacion no puede superar 500 caracteres',
            'evidencias.*.file' => 'Las evidencias deben ser archivos validos',
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

        // Archivos crudos: el Service los persiste y devuelve la metadata
        // (url / path_relativo / nombre_original / extension) que se
        // guarda como JSON en la columna `evidencias`.
        $archivos = $request->file('evidencias', []);
        $archivos = is_array($archivos) ? $archivos : [];

        $result = AnticiposProveedorService::registrar(
            id_proveedor: $id_proveedor,
            id_empresa: (int) $request->input('id_empresa'),
            id_empleado_registro: $idEmpleadoRegistro,
            id_cuenta_bancaria_empresa: $request->input('id_cuenta_bancaria_empresa') !== null
                ? (int) $request->input('id_cuenta_bancaria_empresa')
                : null,
            id_cuenta_bancaria_proveedor: $request->input('id_cuenta_bancaria_proveedor') !== null
                ? (int) $request->input('id_cuenta_bancaria_proveedor')
                : null,
            medio_pago: $medioPago,
            fecha_hora_pago: $request->input('fecha_hora_pago') ?: null,
            numero_operacion: $this->textoOpcional($request->input('numero_operacion')),
            codigo_comprobante: $this->textoOpcional($request->input('codigo_comprobante')),
            observacion: $this->textoOpcional($request->input('observacion')),
            pago_a_terceros: (bool) $request->boolean('pago_a_terceros'),
            saldo: (float) $request->input('saldo'),
            archivos: $archivos,
        );

        return response()->json($result);
    }

    /** Recorta el texto y convierte el vacio en null. */
    private function textoOpcional(mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }
        $trimmed = trim($valor);
        return $trimmed === '' ? null : $trimmed;
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
