<?php

namespace App\Services;

use App\Data\BancosData;
use App\Data\TransportistasCuentasData;
use App\Models\Transportista;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Responses\ApiResponse;

class TransportistasCuentasService
{
    public static function get_cuentas(
        int|array|null $id_transportista = null,
        int|array|null $id_cuenta_bancaria = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ): array {
        return ApiResponse::success(
            TransportistasCuentasData::get_cuentas(
                id_transportista: $id_transportista,
                id_cuenta_bancaria: $id_cuenta_bancaria,
                estado: $estado,
            ),
            'Cuentas bancarias de transportista obtenidas correctamente',
        );
    }

    public static function crear_cuenta(
        int $id_transportista,
        int $id_banco,
        Moneda $moneda,
        string $numero_cuenta,
        ?string $cci,
        bool $es_para_detraccion
    ): array {
        if (!Transportista::query()->where('id', $id_transportista)->exists()) {
            return ApiResponse::error('El transportista no existe');
        }

        if (TransportistasCuentasData::ya_existe($id_transportista, $id_banco, $numero_cuenta)) {
            return ApiResponse::error('Esta cuenta bancaria ya está registrada para este transportista');
        }

        // La detraccion solo existe contra el Banco de la Nacion: una cuenta de
        // otro banco nunca puede recibir el monto retenido por SUNAT.
        if ($es_para_detraccion && !self::es_banco_nacional($id_banco)) {
            return ApiResponse::error('Solo una cuenta del Banco de la Nación puede destinarse a detracción');
        }
        if ($es_para_detraccion && $moneda !== Moneda::PEN) {
            return ApiResponse::error('Las cuentas de detracción solo pueden estar en soles');
        }

        $id = TransportistasCuentasData::crear_cuenta(
            $id_transportista,
            $id_banco,
            $moneda,
            $numero_cuenta,
            $cci ?: '',
            $es_para_detraccion
        );

        return ApiResponse::success(
            self::cuenta_como_array(TransportistasCuentasData::get_cuentas(id_cuenta_bancaria: $id)),
            'Cuenta bancaria registrada correctamente',
        );
    }

    public static function actualizar_cuenta(
        int $id_cuenta_bancaria,
        int $id_banco,
        Moneda $moneda,
        string $numero_cuenta,
        ?string $cci,
        bool $es_para_detraccion
    ): array {
        $id_transportista = TransportistasCuentasData::get_transportista_id_by_cuenta($id_cuenta_bancaria);
        if ($id_transportista === null) {
            return ApiResponse::error('La cuenta bancaria no existe');
        }

        if (TransportistasCuentasData::ya_existe($id_transportista, $id_banco, $numero_cuenta, $id_cuenta_bancaria)) {
            return ApiResponse::error('Esta cuenta bancaria ya está registrada para este transportista');
        }

        if ($es_para_detraccion && !self::es_banco_nacional($id_banco)) {
            return ApiResponse::error('Solo una cuenta del Banco de la Nación puede destinarse a detracción');
        }
        if ($es_para_detraccion && $moneda !== Moneda::PEN) {
            return ApiResponse::error('Las cuentas de detracción solo pueden estar en soles');
        }

        TransportistasCuentasData::actualizar_cuenta(
            $id_cuenta_bancaria,
            $id_banco,
            $moneda,
            $numero_cuenta,
            $cci ?: '',
            $es_para_detraccion
        );

        return ApiResponse::success(
            self::cuenta_como_array(TransportistasCuentasData::get_cuentas(id_cuenta_bancaria: $id_cuenta_bancaria)),
            'Cuenta bancaria actualizada correctamente',
        );
    }

    /**
     * `get_cuentas` devuelve un `stdClass` cuando se filtra por un unico id y
     * una coleccion cuando no. La respuesta JSON debe tener siempre la misma
     * forma, asi que se normaliza a array asociativo.
     *
     * @param stdClass|Collection<array-key, mixed>|array<array-key, mixed> $cuenta
     * @return array<string, mixed>
     */
    private static function cuenta_como_array(mixed $cuenta): array
    {
        if ($cuenta instanceof \stdClass) {
            return (array) $cuenta;
        }
        if ($cuenta instanceof \Illuminate\Support\Collection) {
            $primero = $cuenta->first();
            return $primero instanceof \stdClass ? (array) $primero : (array) $primero;
        }

        return (array) $cuenta;
    }

    private static function es_banco_nacional(int $id_banco): bool
    {
        $banco = BancosData::get_bancos(id_banco: $id_banco);
        if ($banco === []) {
            return false;
        }

        return (int) $banco['es_nacional'] === 1;
    }
}
