<?php

namespace App\Modules\Proveedores\Controllers;

use App\Modules\Proveedores\Services\ProveedoresService;
use App\Shared\Enums\_Generic\TipoEntidad;
use Illuminate\Http\Request;

class ProveedoresController
{
    public function get_proveedores()
    {
        return response()->json(ProveedoresService::get_proveedores());
    }

    public function crear_proveedor(Request $request)
    {
        $request->validate([
            'tipo_entidad' => 'required|string',
            'paraMantenimiento' => 'nullable|boolean',
            'paraTransporte' => 'nullable|boolean',
            'dni' => 'nullable|string|size:8',
            'ruc' => 'nullable|string|size:11',
            'razon_social' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:20',
            'correo' => 'nullable|email|max:100',
        ]);

        $tipo_entidad = TipoEntidad::from($request->input('tipo_entidad'));

        return response()->json(ProveedoresService::crear_proveedor(
            tipoEntidad: $tipo_entidad,
            razonSocial: $request->razon_social,
            paraMantenimiento: (bool) $request->paraMantenimiento,
            paraTransporte: (bool) $request->paraTransporte,
            dni: $request->dni,
            ruc: $request->ruc,
            direccion: $request->direccion,
            telefono: $request->telefono,
            correo: $request->correo,
            cuentas: $request->cuentas ?? []
        ));
    }
}
