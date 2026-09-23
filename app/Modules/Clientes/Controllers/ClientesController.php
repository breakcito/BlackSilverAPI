<?php

namespace App\Modules\Clientes\Controllers;

use App\Modules\Clientes\Services\ClientesService;
use App\Shared\Enums\_Generic\TipoEntidad;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\Request;

class ClientesController
{
    /**
     * Retorna la lista de clientes.
     * Acepta `?para_carbon=true|false`. Si no se envia, NO se filtra y se
     * devuelven tanto logística como carbon.
     */
    public function get_clientes(Request $request)
    {
        $paraCarbon = $request->has('para_carbon')
            ? $request->boolean('para_carbon')
            : null;

        return response()->json(ClientesService::get_clientes(paraCarbon: $paraCarbon));
    }

    /** Valida la entrada y registra un nuevo cliente. */
    public function crear_cliente(Request $request)
    {
        $request->validate([
            'tipo_entidad'      => 'required|string',
            'dni'               => 'nullable|string|size:8',
            // RUC ahora es opcional: el frontend envia ruc=11digitos o dni=8digitos
            // (uno de los dos), o ambos null si no se proporciono documento.
            // El prefijo (10/20) se valida abajo solo si ruc llega con largo 11.
            'ruc'               => 'nullable|string|size:11',
            'razon_social'      => 'required|string|max:255',
            'direccion'         => 'nullable|string|max:255',
            'telefono'          => 'nullable|string|max:20',
            'correo'            => 'nullable|email|max:100',
            'paraCarbon'        => 'nullable|boolean',
        ]);

        $tipo_entidad = TipoEntidad::from($request->input('tipo_entidad'));
        $ruc = $request->input('ruc');
        $ruc = ($ruc === null || $ruc === '') ? null : (string) $ruc;

        // Validacion de prefijo de RUC segun tipo de entidad (solo si llega RUC).
        if ($ruc !== null) {
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
        }

        return response()->json(ClientesService::crear_cliente(
            tipoEntidad: $tipo_entidad->value,
            dni: $request->dni,
            ruc: $ruc,
            razonSocial: $request->razon_social,
            direccion: $request->direccion,
            telefono: $request->telefono,
            correo: $request->correo,
            paraCarbon: $request->boolean('paraCarbon'),
        ));
    }

    /**
     * Actualizar campos administrativos de un cliente.
     * El estado se gestiona por eliminar_cliente (soft-delete) — no se expone aquí.
     * `paraCarbon` NO se acepta: define la pestaña del cliente y se congela al crear.
     */
    public function actualizar_cliente(Request $request, int $id_cliente)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'tipo_entidad'      => 'required|string',
            'dni'               => 'nullable|string|size:8',
            // RUC opcional (mismo criterio que crear). El frontend mapea 8digitos
            // al campo dni y 11digitos al campo ruc.
            'ruc'               => 'nullable|string|size:11',
            'razon_social'      => 'required|string|max:255',
            'direccion'         => 'nullable|string|max:255',
            'telefono'          => 'nullable|string|max:20',
            'correo'            => 'nullable|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $tipo_entidad = TipoEntidad::from($request->input('tipo_entidad'));
        $ruc = $request->input('ruc');
        $ruc = ($ruc === null || $ruc === '') ? null : (string) $ruc;

        // Mismas reglas de prefijo de RUC que en el registro (solo si llega).
        if ($ruc !== null) {
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
