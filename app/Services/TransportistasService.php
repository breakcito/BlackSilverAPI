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

        // Patron Proveedores: RUC obligatorio (11 digitos, prefijo 10/20 segun
        // tipo_entidad); DNI opcional pero validado si llega.
        if (empty($ruc) || !preg_match('/^\d{11}$/', $ruc)) {
            return ApiResponse::error('El RUC debe tener 11 dígitos');
        }
        if ($tipo_enum === TipoEntidad::Juridica && !str_starts_with($ruc, '20')) {
            return ApiResponse::error('El RUC de una persona jurídica debe comenzar con 20');
        }
        if ($tipo_enum === TipoEntidad::Natural && !str_starts_with($ruc, '10')) {
            return ApiResponse::error('El RUC de una persona natural debe comenzar con 10');
        }
        if (!empty($dni) && !preg_match('/^\d{8}$/', $dni)) {
            return ApiResponse::error('El DNI debe tener 8 dígitos');
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
