<?php

namespace App\Modules\Clientes\Controllers;

use App\Modules\Clientes\Services\ClientesService;
use App\Shared\Enums\_Generic\TipoEntidad;
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
            'tipo_entidad'      => 'required|string',
            'dni'               => 'nullable|string|size:8',
            // RUC obligatorio (11 digitos, prefijo segun tipo_entidad — se valida abajo).
            'ruc'               => 'required|string|size:11',
            'razon_social'      => 'required|string|max:255',
            'direccion'         => 'nullable|string|max:255',
            'telefono'          => 'nullable|string|max:20',
            'correo'            => 'nullable|email|max:100',
        ]);

        $tipo_entidad = TipoEntidad::from($request->input('tipo_entidad'));
        $ruc = (string) $request->input('ruc');

        // Validacion de prefijo de RUC segun tipo de entidad (mismo patron que Proveedores).
        if ($tipo_entidad === TipoEntidad::Juridica && !str_starts_with($ruc, '20')) {
            return response()->json(
                ApiResponse::error('El RUC de una persona juridica debe comenzar con 20'),
                422,
            );
        }
        if ($tipo_entidad === TipoEntidad::Natural && !str_starts_with($ruc, '10')) {
            return response()->json(
                ApiResponse::error('El RUC de una persona natural debe comenzar con 10'),
                422,
            );
        }

        return response()->json(ClientesService::crear_cliente(
            $tipo_entidad->value,
            $request->dni,
            $ruc,
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
            'tipo_entidad'      => 'required|string',
            'dni'               => 'nullable|string|size:8',
            'ruc'               => 'required|string|size:11',
            'razon_social'      => 'required|string|max:255',
            'direccion'         => 'nullable|string|max:255',
            'telefono'          => 'nullable|string|max:20',
            'correo'            => 'nullable|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $tipo_entidad = TipoEntidad::from($request->input('tipo_entidad'));
        $ruc = (string) $request->input('ruc');

        // Mismas reglas de prefijo de RUC que en el registro.
        if ($tipo_entidad === TipoEntidad::Juridica && !str_starts_with($ruc, '20')) {
            return response()->json(
                ApiResponse::error('El RUC de una persona juridica debe comenzar con 20'),
                422,
            );
        }
        if ($tipo_entidad === TipoEntidad::Natural && !str_starts_with($ruc, '10')) {
            return response()->json(
                ApiResponse::error('El RUC de una persona natural debe comenzar con 10'),
                422,
            );
        }

        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = is_object($authUser) && isset($authUser->id_empleado) ? (int) $authUser->id_empleado : null;
        $nombreEmpleado = is_object($authUser)
            ? trim(($authUser->nombre ?? '') . ' ' . ($authUser->apellido ?? '')) ?: null
            : null;

        $result = ClientesService::actualizar_cliente(
            id_cliente: $id_cliente,
            tipo_entidad: $tipo_entidad->value,
            dni: $this->emptyToNull($request->input('dni')),
            ruc: $ruc,
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
