<?php

namespace App\Modules\AnticiposCliente\Controllers;

use App\Modules\AnticiposCliente\Services\AnticiposClienteService;
use App\Shared\Enums\AnticipoCliente\MedioPago;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class AnticiposClienteController extends Controller
{
    public function listar_por_cliente(int $id_cliente): JsonResponse
    {
        return response()->json(
            AnticiposClienteService::get_por_cliente($id_cliente)
        );
    }

    /**
     * Registra un anticipo recibido de un cliente de carbon.
     *
     * El request es `multipart/form-data`: los adjuntos llegan en el campo
     * `evidencias[]` como `UploadedFile` y los guarda el Service via
     * `ArchivoHelper::guardarArchivos()` (mismo patron que los anticipos a
     * proveedor). El backend NO acepta URLs ni paths de archivos: los
     * genera al persistir, asi nadie puede inventarse una evidencia que
     * nunca se subio.
     *
     * Campos escalares:
     * {
     *   id_empresa: int,                      // empresa donde entra el dinero
     *   id_cuenta_bancaria_empresa?: int,     // cuenta donde se recibio (opcional en efectivo)
     *   medio_pago: 'Transferencia' | 'Depósito' | 'Efectivo',
     *   fecha_hora_pago?: 'YYYY-MM-DD'        // se completa a '00:00:00' al persistir
     *                       | 'YYYY-MM-DD HH:MM:SS',
     *   numero_operacion?: string(128),
     *   codigo_comprobante?: string(128),
     *   observacion?: string,
     *   saldo: float > 0                      // se guarda en saldo_inicial y saldo_actual,
     *   evidencias[]: UploadedFile            // opcional
     * }
     *
     * `id_cliente` viene de la ruta y `id_empleado_registro` sale del JWT
     * (inyectado por el middleware `auth.jwt.custom` en
     * `$request->attributes->get('auth_user')`). Ninguno de los dos se
     * acepta desde el cuerpo del request.
     */
    public function registrar(Request $request, int $id_cliente): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_empresa' => 'required|integer|min:1',
            'id_cuenta_bancaria_empresa' => 'nullable|integer|min:1',
            'medio_pago' => ['required', new Enum(MedioPago::class)],
            'fecha_hora_pago' => [
                'nullable',
                'string',
                // Acepta `YYYY-MM-DD` o `YYYY-MM-DD HH:MM:SS`. Lo normalizamos
                // despues: si vino solo fecha le pegamos ` 00:00:00` para
                // satisfacer el DATETIME de MySQL.
                'regex:/^\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}:\d{2})?$/',
            ],
            'numero_operacion' => 'nullable|string|max:128',
            'codigo_comprobante' => 'nullable|string|max:128',
            'observacion' => 'nullable|string|max:65535',
            // DECIMAL(12,2) en la tabla: 10 enteros + 2 decimales.
            'saldo' => 'required|numeric|min:0.01|max:9999999999.99',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ], [
            'id_empresa.required' => 'La empresa es obligatoria',
            'medio_pago.required' => 'El medio de pago es obligatorio',
            'saldo.required' => 'El saldo es obligatorio',
            'saldo.min' => 'El saldo debe ser mayor a 0',
            'saldo.max' => 'El saldo no puede superar S/ 9,999,999,999.99',
            'fecha_hora_pago.regex' => 'Formato invalido (use YYYY-MM-DD o YYYY-MM-DD HH:MM:SS)',
            'numero_operacion.max' => 'El numero de operacion no puede superar 128 caracteres',
            'codigo_comprobante.max' => 'El comprobante no puede superar 128 caracteres',
            'observacion.max' => 'La observacion es demasiado larga',
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

        $medioPago = MedioPago::from((string) $request->input('medio_pago'));

        // Archivos crudos: el Service los persiste y devuelve la metadata
        // (url / path_relativo / nombre_original / extension) que se
        // guarda como JSON en la columna `evidencias`.
        $archivos = $request->file('evidencias', []);
        $archivos = is_array($archivos) ? $archivos : [];

        $result = AnticiposClienteService::registrar(
            id_cliente: $id_cliente,
            id_empresa: (int) $request->input('id_empresa'),
            id_empleado_registro: $idEmpleadoRegistro,
            id_cuenta_bancaria_empresa: $request->input('id_cuenta_bancaria_empresa') !== null
                ? (int) $request->input('id_cuenta_bancaria_empresa')
                : null,
            medio_pago: $medioPago,
            fecha_hora_pago: $this->normalizarFechaHoraPago($request->input('fecha_hora_pago')),
            numero_operacion: $this->textoOpcional($request->input('numero_operacion')),
            codigo_comprobante: $this->textoOpcional($request->input('codigo_comprobante')),
            observacion: $this->textoOpcional($request->input('observacion')),
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
     * Acepta `YYYY-MM-DD` o `YYYY-MM-DD HH:MM:SS`; en el primer caso, le
     * pega ` 00:00:00` para satisfacer el formato DATETIME de la columna.
     * Devuelve `null` si el input viene vacio, null o no es string.
     */
    private function normalizarFechaHoraPago(mixed $valor): ?string
    {
        if (!is_string($valor)) {
            return null;
        }
        $trimmed = trim($valor);
        if ($trimmed === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            return $trimmed . ' 00:00:00';
        }
        return $trimmed;
    }

    /**
     * Anular un anticipo. El id del empleado que anula sale del JWT,
     * igual que el de registro.
     */
    public function anular(Request $request, int $id_cliente, int $id_anticipo): JsonResponse
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
            AnticiposClienteService::anular($id_cliente, $id_anticipo, $idEmpleadoAnulacion)
        );
    }
}
