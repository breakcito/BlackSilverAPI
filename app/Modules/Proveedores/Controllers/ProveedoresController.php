<?php

namespace App\Modules\Proveedores\Controllers;

use App\Modules\Proveedores\Services\ProveedoresService;
use App\Shared\Enums\_Generic\TipoEntidad;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\Request;

class ProveedoresController
{
    public function get_proveedores(Request $request)
    {
        // $request->boolean() parsea correctamente "true"/"1" como true y
        // "false"/"0" como false. (bool) "false" devolveria true en PHP,
        // por eso NO se usa el cast directo.
        $paraCarbon = $request->has('para_carbon')
            ? $request->boolean('para_carbon')
            : null;

        return response()->json(ProveedoresService::get_proveedores(paraCarbon: $paraCarbon));
    }

    public function crear_proveedor(Request $request)
    {
        $request->validate([
            'tipo_entidad' => 'required|string',
            'paraMantenimiento' => 'nullable|boolean',
            'paraTransporte' => 'nullable|boolean',
            'paraCarbon' => 'nullable|boolean',
            // RUC obligatorio (11 digitos, prefijo segun tipo_entidad — se valida abajo).
            'ruc' => 'required|string|size:11',
            // DNI opcional; si llega, debe tener 8 digitos.
            'dni' => 'nullable|string|size:8',
            'razon_social' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:100',
        ]);

        $tipo_entidad = TipoEntidad::from($request->input('tipo_entidad'));
        $ruc = (string) $request->input('ruc');

        // Validacion de prefijo de RUC segun tipo de entidad.
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

        return response()->json(ProveedoresService::crear_proveedor(
            tipoEntidad: $tipo_entidad,
            razonSocial: $request->razon_social,
            paraMantenimiento: (bool) $request->paraMantenimiento,
            paraTransporte: (bool) $request->paraTransporte,
            dni: $request->dni,
            ruc: $ruc,
            direccion: $request->direccion,
            telefono: $request->telefono,
            correo: $request->correo,
            paraCarbon: (bool) $request->paraCarbon,
            cuentas: $request->cuentas ?? []
        ));
    }
}
