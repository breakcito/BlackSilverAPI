<?php

namespace App\Modules\Clientes\Controllers;

use App\Modules\Clientes\Services\ClientesService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\Request;

class ClientesController
{
    /** Retorna la lista completa de clientes. */
    public function get_clientes()
    {
        return response()->json(ClientesService::get_clientes());
    }

    /** Valida la entrada y registra un nuevo cliente. */
    public function crear_cliente(Request $request)
    {
        $request->validate([
            'tipo_entidad'      => 'nullable|string|max:64',
            'dni'               => 'nullable|string|size:8',
            'ruc'               => 'nullable|string|size:11',
            'razon_social'      => 'required|string|max:255',
            'direccion'         => 'nullable|string|max:255',
            'telefono'          => 'nullable|string|max:20',
            'correo'            => 'nullable|email|max:100',
        ]);

        return response()->json(ClientesService::crear_cliente(
            $request->tipo_entidad,
            $request->dni,
            $request->ruc,
            $request->razon_social,
            $request->direccion,
            $request->telefono,
            $request->correo
        ));
    }

    /**
     * Actualizar campos administrativos de un cliente.
     * El estado se gestiona por eliminar_cliente (soft-delete) — no se expone aquí.
     */
    public function actualizar_cliente(Request $request, int $id_cliente)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'tipo_entidad'      => 'nullable|string|max:64',
            'dni'               => 'nullable|string|size:8',
            'ruc'               => 'nullable|string|size:11',
            'razon_social'      => 'required|string|max:255',
            'direccion'         => 'nullable|string|max:255',
            'telefono'          => 'nullable|string|max:20',
            'correo'            => 'nullable|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = is_object($authUser) && isset($authUser->id_empleado) ? (int) $authUser->id_empleado : null;
        $nombreEmpleado = is_object($authUser)
            ? trim(($authUser->nombre ?? '') . ' ' . ($authUser->apellido ?? '')) ?: null
            : null;

        $result = ClientesService::actualizar_cliente(
            id_cliente: $id_cliente,
            tipo_entidad: $this->emptyToNull($request->input('tipo_entidad')),
            dni: $this->emptyToNull($request->input('dni')),
            ruc: $this->emptyToNull($request->input('ruc')),
            razon_social: (string) ($request->input('razon_social') ?? ''),
            direccion: $this->emptyToNull($request->input('direccion')),
            telefono: $this->emptyToNull($request->input('telefono')),
            correo: $this->emptyToNull($request->input('correo')),
            id_empleado: $idEmpleado,
            nombre_empleado: $nombreEmpleado,
        );

        return response()->json($result);
    }

    /**
     * Desactivar (soft delete) un cliente. Cambia estado a Inactivo.
     */
    public function eliminar_cliente(Request $request, int $id_cliente)
    {
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = is_object($authUser) && isset($authUser->id_empleado) ? (int) $authUser->id_empleado : null;
        $nombreEmpleado = is_object($authUser)
            ? trim(($authUser->nombre ?? '') . ' ' . ($authUser->apellido ?? '')) ?: null
            : null;

        $result = ClientesService::eliminar_cliente(
            id_cliente: $id_cliente,
            id_empleado: $idEmpleado,
            nombre_empleado: $nombreEmpleado,
        );

        return response()->json($result);
    }

    private function emptyToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
