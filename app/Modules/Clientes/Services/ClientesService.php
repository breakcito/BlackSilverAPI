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

    /**
     * Actualizar campos administrativos de un cliente (NO estado).
     * Si se recibe id_empleado + nombre_empleado se calcula diff y se apendea
     * a cambios_log para trazabilidad.
     */
    public static function actualizar_cliente(
        int $id_cliente,
        ?string $tipo_entidad,
        ?string $dni,
        ?string $ruc,
        string $razon_social,
        ?string $direccion,
        ?string $telefono,
        ?string $correo,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ) {
        $existe = ClientesData::get_cliente_by_id($id_cliente);
        if (!$existe) {
            return ApiResponse::error('El cliente que intenta editar no existe.');
        }

        ClientesData::actualizar_cliente(
            id_cliente: $id_cliente,
            tipo_entidad: $tipo_entidad,
            dni: $dni,
            ruc: $ruc,
            razon_social: $razon_social,
            direccion: $direccion,
            telefono: $telefono,
            correo: $correo,
            id_empleado: $id_empleado,
            nombre_empleado: $nombre_empleado,
        );

        return ApiResponse::success(
            ClientesData::get_cliente_by_id($id_cliente),
            'Cliente actualizado correctamente',
        );
    }

    /**
     * Desactivar (soft delete) un cliente. Cambia estado a Inactivo y registra
     * la accion en cambios_log para trazabilidad.
     */
    public static function eliminar_cliente(
        int $id_cliente,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ) {
        $existe = ClientesData::get_cliente_by_id($id_cliente);
        if (!$existe) {
            return ApiResponse::error('El cliente que intenta eliminar no existe.');
        }

        ClientesData::eliminar_cliente(
            id_cliente: $id_cliente,
            id_empleado: $id_empleado,
            nombre_empleado: $nombre_empleado,
        );

        return ApiResponse::success(
            ClientesData::get_cliente_by_id($id_cliente),
            'Cliente eliminado correctamente',
        );
    }
}