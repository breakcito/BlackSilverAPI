<?php

namespace App\Modules\AnticiposProveedor\Services;

use App\Modules\AnticiposProveedor\Data\AnticiposProveedorData;
use App\Shared\Enums\AnticipoProveedor\EstadoAnticipo;
use App\Shared\Enums\AnticipoProveedor\MedioPago;
use App\Shared\Responses\ApiResponse;

class AnticiposProveedorService
{
    public static function get_por_proveedor(int $id_proveedor): array
    {
        $data = AnticiposProveedorData::get_por_proveedor($id_proveedor);
        return ApiResponse::success($data, 'Anticipos del proveedor');
    }

    /**
     * Registra un anticipo. La validacion de negocio vive aqui (no en el
     * controller): cuenta + fecha + operacion son obligatorios si medio_pago
     * es Transferencia o Deposito.
     *
     * `saldo_inicial` y `saldo_actual` reciben el mismo valor: el form
     * expone un solo campo (lo que se da es lo que queda disponible al
     * inicio; los descuentos futuros se gestionan fuera de este modulo).
     *
     * El `id_empleado_registro` viene del JWT (lo inyecta el middleware).
     * El `id_empleado_anulacion` queda null hasta que se ejecute la
     * accion de anular.
     *
     * `evidencias` es la lista de IArchivo (subidos previamente al storage
     * por el FE). El Data la persiste como JSON.
     *
     * @param array<int, array{url:string,path_relativo:string,nombre_original?:?string,extension?:?string}>|null $evidencias
     */
    public static function registrar(
        int $id_proveedor,
        int $id_empresa,
        int $id_empleado_registro,
        ?int $id_cuenta_bancaria_empresa,
        ?MedioPago $medio_pago,
        ?string $fecha_hora_pago,
        ?string $numero_operacion,
        float $saldo,
        ?array $evidencias
    ): array {
        if ($saldo <= 0) {
            return ApiResponse::error('El saldo inicial debe ser mayor a 0');
        }

        if ($medio_pago !== null) {
            $requiereBanco = $medio_pago === MedioPago::Transferencia
                || $medio_pago === MedioPago::Deposito;

            if ($requiereBanco) {
                if ($id_cuenta_bancaria_empresa === null) {
                    return ApiResponse::error('Para ' . $medio_pago->value . ' debe seleccionar la cuenta bancaria de la empresa');
                }
                if (empty($fecha_hora_pago)) {
                    return ApiResponse::error('Para ' . $medio_pago->value . ' debe indicar la fecha y hora del pago');
                }
                if (empty($numero_operacion)) {
                    return ApiResponse::error('Para ' . $medio_pago->value . ' debe indicar el numero de operacion');
                }
            }
        }

        $id = AnticiposProveedorData::insertar(
            id_empresa: $id_empresa,
            id_proveedor: $id_proveedor,
            id_empleado_registro: $id_empleado_registro,
            id_cuenta_bancaria_empresa: $id_cuenta_bancaria_empresa,
            medio_pago: $medio_pago?->value,
            fecha_hora_pago: $fecha_hora_pago,
            numero_operacion: $numero_operacion,
            saldo_inicial: $saldo,
            saldo_actual: $saldo,
            evidencias: $evidencias,
            estado: EstadoAnticipo::ConSaldo->value,
        );

        $item = AnticiposProveedorData::get_por_id($id);
        return ApiResponse::success($item, 'Anticipo registrado correctamente');
    }

    /**
     * Soft delete: marca el anticipo como anulado, registra quien y
     * cuando. No permite anular un anticipo ya anulado.
     */
    public static function anular(int $id_anticipo, int $id_empleado_anulacion): array
    {
        $existe = AnticiposProveedorData::get_por_id($id_anticipo);
        if (! $existe) {
            return ApiResponse::error('Anticipo no encontrado');
        }

        if ((int) ($existe->esta_anulado ?? 0) === 1) {
            return ApiResponse::error('El anticipo ya se encuentra anulado');
        }

        AnticiposProveedorData::anular($id_anticipo, $id_empleado_anulacion);
        $item = AnticiposProveedorData::get_por_id($id_anticipo);
        return ApiResponse::success($item, 'Anticipo anulado correctamente');
    }
}
