<?php

namespace App\Modules\Clientes\Services;

use App\Modules\Clientes\Data\ClientesData;
use App\Shared\Responses\ApiResponse;

class ClientesService
{
    /** Obtiene y retorna todos los clientes registrados. */
    public static function get_clientes(): array
    {
        $data = ClientesData::get_clientes();
        return ApiResponse::success($data, 'Clientes obtenidos correctamente');
    }

    /** Crea un nuevo cliente y retorna el registro recién creado. */
    public static function crear_cliente(
        ?string $tipoEntidad,
        ?string $dni,
        ?string $ruc,
        string $razonSocial,
        ?string $direccion,
        ?string $telefono,
        ?string $correo
    ): array {
        $id = ClientesData::crear_cliente(
            $tipoEntidad,
            $dni,
            $ruc,
            $razonSocial,
            $direccion,
            $telefono,
            $correo
        );

        $nuevo = ClientesData::get_cliente_by_id($id);
        return ApiResponse::success($nuevo, 'Cliente registrado correctamente');
    }
}
