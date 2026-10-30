<?php

namespace App\Modules\AnticiposCliente\Services;

use App\Modules\AnticiposCliente\Data\AnticiposClienteData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\AnticipoCliente\EstadoAnticipo;
use App\Shared\Enums\AnticipoCliente\MedioPago;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class AnticiposClienteService
{
    /**
     * Lista los anticipos recibidos de un cliente.
     */
    public static function get_por_cliente(int $id_cliente): array
    {
        $data = AnticiposClienteData::get_por_cliente($id_cliente);
        return ApiResponse::success($data, 'Anticipos del cliente');
    }

    /**
     * Registra un anticipo RECIBIDO de un cliente de carbon.
     *
     * A diferencia del anticipo a proveedor, aqui la plata ENTRA a la
     * empresa: por eso solo existe la cuenta bancaria de la EMPRESA (donde
     * se recibio el dinero) y no hay cuenta destino del cliente ni
     * cambista de por medio.
     *
     * Las validaciones de negocio viven aqui y no en el controller:
     * - el saldo debe ser mayor a 0;
     * - el cliente debe existir, estar activo y ser de carbon
     *   (`para_carbon = 1`), para que el anticipo no pueda colgar de un
     *   cliente de logistica;
     * - la empresa debe existir;
     * - para Transferencia y Deposito son obligatorios la cuenta bancaria
     *   de la empresa, la fecha/hora del pago y el numero de operacion;
     *   para Efectivo la cuenta es opcional y no hay operacion bancaria;
     * - si viene una cuenta bancaria, debe pertenecer a la empresa.
     *
     * `saldo_inicial` y `saldo_actual` reciben el mismo valor: el form
     * expone un solo campo (lo que se da es lo que queda disponible al
     * inicio; los descuentos futuros se gestionan fuera de este modulo).
     *
     * `id_empleado_registro` viene del JWT (lo inyecta el middleware
     * `auth.jwt.custom`). El cliente NUNCA lo manda. El
     * `id_empleado_anulacion` queda null hasta que se ejecute la accion
     * de anular.
     *
     * @param array<int, \Illuminate\Http\UploadedFile> $archivos
     *   Evidencias tal como llegan en el multipart. Se persisten con
     *   `ArchivoHelper::guardarArchivos()` (carpeta `anticipos-cliente`,
     *   subcarpetada por fecha) y la metadata resultante se guarda como
     *   JSON en la columna `evidencias`. Si el guardado de archivos falla
     *   se aborta el registro para no dejar un anticipo sin sus evidencias.
     */
    public static function registrar(
        int $id_cliente,
        int $id_empresa,
        int $id_empleado_registro,
        ?int $id_cuenta_bancaria_empresa,
        MedioPago $medio_pago,
        ?string $fecha_hora_pago,
        ?string $numero_operacion,
        ?string $codigo_comprobante,
        ?string $observacion,
        float $saldo,
        array $archivos = []
    ): array {
        if ($saldo <= 0) {
            return ApiResponse::error('El saldo inicial debe ser mayor a 0');
        }

        // 1. El cliente debe existir, estar activo y ser de carbon.
        $cliente = AnticiposClienteData::get_cliente_carbon($id_cliente);
        if (!$cliente) {
            return ApiResponse::error('Cliente no encontrado');
        }
        if ((int) $cliente->para_carbon !== 1) {
            return ApiResponse::error('Los anticipos solo pueden registrarse para clientes de carbón');
        }
        if ($cliente->estado !== EstadoBase::Activo->value) {
            return ApiResponse::error('El cliente no se encuentra activo');
        }

        // 2. La empresa destino del dinero debe existir.
        if (!AnticiposClienteData::get_empresa($id_empresa)) {
            return ApiResponse::error('La empresa no existe');
        }

        // 3. Reglas por medio de pago.
        $requiereBanco = $medio_pago === MedioPago::Transferencia
            || $medio_pago === MedioPago::Deposito;

        if ($requiereBanco) {
            if ($id_cuenta_bancaria_empresa === null) {
                return ApiResponse::error(
                    'Para ' . $medio_pago->value . ' debe seleccionar la cuenta bancaria donde se recibió el dinero'
                );
            }
            if (empty($fecha_hora_pago)) {
                return ApiResponse::error('Para ' . $medio_pago->value . ' debe indicar la fecha y hora del pago');
            }
            if (empty($numero_operacion)) {
                return ApiResponse::error('Para ' . $medio_pago->value . ' debe indicar el numero de operacion');
            }
        }

        // 4. Si se informo una cuenta, tiene que ser de esa empresa.
        if ($id_cuenta_bancaria_empresa !== null) {
            $cuenta = AnticiposClienteData::get_cuenta_bancaria_de_empresa(
                $id_cuenta_bancaria_empresa,
                $id_empresa
            );
            if (!$cuenta) {
                return ApiResponse::error('La cuenta bancaria no pertenece a la empresa seleccionada');
            }
        }

        // 5. Persistencia de evidencias antes del insert: si el guardado
        // falla se aborta el registro para no dejar un anticipo sin sus
        // evidencias (mismo criterio que los anticipos a proveedor).
        $evidencias = null;
        if (count($archivos) > 0) {
            $evidencias = ArchivoHelper::guardarArchivos('anticipos-cliente', $archivos);
            if (count($evidencias) === 0) {
                return ApiResponse::error('No se pudieron guardar las evidencias del anticipo');
            }
        }

        $id = DB::transaction(fn() => AnticiposClienteData::insertar(
            id_empresa: $id_empresa,
            id_cliente: $id_cliente,
            id_empleado_registro: $id_empleado_registro,
            id_cuenta_bancaria_empresa: $id_cuenta_bancaria_empresa,
            medio_pago: $medio_pago->value,
            fecha_hora_pago: $fecha_hora_pago,
            numero_operacion: $numero_operacion,
            codigo_comprobante: $codigo_comprobante,
            observacion: $observacion,
            saldo_inicial: $saldo,
            saldo_actual: $saldo,
            evidencias: $evidencias,
            estado: EstadoAnticipo::ConSaldo->value,
        ));

        $item = AnticiposClienteData::get_por_id($id);
        return ApiResponse::success($item, 'Anticipo registrado correctamente');
    }

    /**
     * Soft delete: marca el anticipo como anulado, registra quien y
     * cuando. No permite anular un anticipo ya anulado ni anular el
     * anticipo de otro cliente.
     */
    public static function anular(int $id_cliente, int $id_anticipo, int $id_empleado_anulacion): array
    {
        $existe = AnticiposClienteData::get_por_id($id_anticipo);
        if (!$existe) {
            return ApiResponse::error('Anticipo no encontrado');
        }

        if ((int) $existe->id_cliente !== $id_cliente) {
            return ApiResponse::error('El anticipo no pertenece a este cliente');
        }

        if ((int) ($existe->esta_anulado ?? 0) === 1) {
            return ApiResponse::error('El anticipo ya se encuentra anulado');
        }

        AnticiposClienteData::anular($id_anticipo, $id_empleado_anulacion);
        $item = AnticiposClienteData::get_por_id($id_anticipo);
        return ApiResponse::success($item, 'Anticipo anulado correctamente');
    }
}
