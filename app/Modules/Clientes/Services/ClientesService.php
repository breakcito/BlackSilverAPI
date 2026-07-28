<?php

namespace App\Modules\Clientes\Services;

use App\Modules\Clientes\Data\ClientesData;
use App\Modules\Clientes\Data\CuentasBancariasData;
use App\Shared\Responses\ApiResponse;

class ClientesService
{
    /** Obtiene y retorna todos los clientes registrados con sus cuentas bancarias. */
    public static function get_clientes(): array
    {
        $clientes = ClientesData::get_clientes();

        if (!is_array($clientes) || empty($clientes)) {
            return ApiResponse::success($clientes, 'Clientes obtenidos correctamente');
        }

        $ids = array_map(fn($c) => (int) $c->id_cliente, $clientes);

        // Convertimos el array en una Colección de Laravel
        $cuentas = collect(CuentasBancariasData::get_cuentas_bancarias(ids_cliente: $ids));

        foreach ($clientes as $cliente) {
            $cliente->cuentas_bancarias = $cuentas->where('id_cliente', $cliente->id_cliente)->values();
        }

        return ApiResponse::success($clientes, 'Clientes obtenidos correctamente');
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