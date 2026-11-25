<?php

namespace App\Services;

use App\Data\TransportistasData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Enums\_Generic\TipoEntidad;
use App\Shared\Responses\ApiResponse;

class TransportistasService
{
    public static function get_transportistas(
        ?int $id_transportista = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ): array {
        return ApiResponse::success(
            TransportistasData::get_transportistas(
                id_transportista: $id_transportista,
                estado: $estado,
            ),
            'Transportistas obtenidos correctamente',
        );
    }

    public static function crear_transportista(
        string $tipo_entidad,
        string $razon_social,
        ?string $ruc,
        ?string $dni,
        ?string $telefono,
    ): array {
        $razon_social = trim($razon_social);
        if ($razon_social === '') {
            return ApiResponse::error('La razón social es obligatoria');
        }

        $tipo_enum = TipoEntidad::tryFrom($tipo_entidad);
        if ($tipo_enum === null) {
            return ApiResponse::error('Tipo de entidad inválido');
        }

        // Reglas de identificacion segun tipo de entidad:
        //   Juridica -> RUC (11 digitos, prefijo 20).
        //   Natural  -> RUC (11 digitos, prefijo 10).
        // El transportista SIEMPRE se identifica con RUC, tambien la persona
        // natural: es lo que exige SUNAT para emitir guides y comprobantes de
        // transporte, y es el dato con el que se concilia el flete.
        if ($tipo_enum === TipoEntidad::Juridica) {
            if (empty($ruc) || !preg_match('/^\d{11}$/', $ruc)) {
                return ApiResponse::error('El RUC de la persona jurídica debe tener 11 dígitos');
            }
            if (!str_starts_with($ruc, '20')) {
                return ApiResponse::error('El RUC de una persona jurídica debe comenzar con 20');
            }
        } else {
            // Natural
            if (empty($ruc) || !preg_match('/^\d{11}$/', $ruc)) {
                return ApiResponse::error('El RUC de la persona natural debe tener 11 dígitos');
            }
            if (!str_starts_with($ruc, '10')) {
                return ApiResponse::error('El RUC de una persona natural debe comenzar con 10');
            }
        }

        if (TransportistasData::existe_transportista($razon_social, $ruc, $dni)) {
            return ApiResponse::error('Ya existe un transportista con esos datos');
        }

        $id = TransportistasData::crear_transportista(
            tipo_entidad: $tipo_enum->value,
            razon_social: $razon_social,
            ruc: $ruc,
            dni: $dni,
            telefono: $telefono,
        );

        return ApiResponse::success(
            TransportistasData::get_transportistas(id_transportista: $id),
            'Transportista registrado correctamente',
        );
    }
}
